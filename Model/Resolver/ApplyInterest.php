<?php
/**
 * PagBank Payment Magento Module.
 *
 * Copyright © 2023 PagBank. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

declare(strict_types=1);

namespace PagBank\PaymentMagento\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterface;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterfaceFactory;
use PagBank\PaymentMagento\Api\Data\InstallmentSelectedInterface;
use PagBank\PaymentMagento\Api\Data\InstallmentSelectedInterfaceFactory;
use PagBank\PaymentMagento\Api\InterestManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ApplyInterest Resolver - Calculate and apply interest to cart for selected installment.
 */
class ApplyInterest implements ResolverInterface
{
    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartTotalRepositoryInterface
     */
    private $cartTotalRepository;

    /**
     * @var CreditCardBinInterfaceFactory
     */
    private $creditCardBinFactory;

    /**
     * @var InstallmentSelectedInterfaceFactory
     */
    private $installmentSelectedFactory;

    /**
     * @var InterestManagementInterface
     */
    private $interestManagement;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param CartRepositoryInterface $cartRepository
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param CreditCardBinInterfaceFactory $creditCardBinFactory
     * @param InstallmentSelectedInterfaceFactory $installmentSelectedFactory
     * @param InterestManagementInterface $interestManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository,
        CartTotalRepositoryInterface $cartTotalRepository,
        CreditCardBinInterfaceFactory $creditCardBinFactory,
        InstallmentSelectedInterfaceFactory $installmentSelectedFactory,
        InterestManagementInterface $interestManagement,
        LoggerInterface $logger
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->creditCardBinFactory = $creditCardBinFactory;
        $this->installmentSelectedFactory = $installmentSelectedFactory;
        $this->interestManagement = $interestManagement;
        $this->logger = $logger;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|\Magento\Framework\GraphQl\Query\Resolver\Value|mixed
     * @throws \Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $this->logger->debug('ApplyInterest resolver called with args: ' . json_encode($args));
        
        // Verificar autenticação
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('Required parameter "input" is missing or invalid.'));
        }

        $input = $args['input'];
        $cartId = $input['cart_id'] ?? null;

        if (!isset($input['credit_card_bin']) 
            || !isset($input['credit_card_bin']['credit_card_bin']) 
            || empty($input['credit_card_bin']['credit_card_bin'])
        ) {
            throw new GraphQlInputException(__('Required parameter "credit_card_bin" is missing or empty.'));
        }

        if (!isset($input['installment_selected']) 
            || !isset($input['installment_selected']['installment_selected']) 
            || empty($input['installment_selected']['installment_selected'])
        ) {
            throw new GraphQlInputException(__('Required parameter "installment_selected" is missing or empty.'));
        }

        try {
            if (!$cartId) {
                $cartId = (string)$context->getUserId();
            } else {
                try {
                    $cartId = (string)$this->maskedQuoteIdToQuoteId->execute($cartId);
                } catch (\Exception $e) {
                    $this->logger->error('Error converting masked quote ID: ' . $e->getMessage());
                    throw new GraphQlInputException(__('Could not find a cart with the provided cart_id.'));
                }
            }

            // Validar se o carrinho existe
            try {
                $quote = $this->cartRepository->get((int)$cartId);
                if (!$quote->getId()) {
                    throw new GraphQlNoSuchEntityException(__('Cart with ID "%1" does not exist.', $cartId));
                }
                
                if (!$quote->getItemsCount()) {
                    throw new GraphQlInputException(__('Cart is empty.'));
                }
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                throw new GraphQlNoSuchEntityException(__('Cart with ID "%1" does not exist.', $cartId));
            }

            // Verificar e validar o bin do cartão
            $creditCardBin = $input['credit_card_bin']['credit_card_bin'];
            if (!preg_match('/^\d+$/', $creditCardBin)) {
                throw new GraphQlInputException(__('Invalid credit_card_bin format. Must contain only digits.'));
            }

            /** @var CreditCardBinInterface $creditCardBinObj */
            $creditCardBinObj = $this->creditCardBinFactory->create();
            $creditCardBinObj->setCreditCardBin($creditCardBin);

            // Verificar e validar o número de parcelas
            $installmentSelected = (int)$input['installment_selected']['installment_selected'];
            if ($installmentSelected <= 0) {
                throw new GraphQlInputException(__('Invalid installment number. Must be a positive integer.'));
            }

            /** @var InstallmentSelectedInterface $installmentSelectedObj */
            $installmentSelectedObj = $this->installmentSelectedFactory->create();
            $installmentSelectedObj->setInstallmentSelected($installmentSelected);

            // Aplicar juros
            $cartTotals = $this->interestManagement->generatePagBankInterest(
                (int)$cartId,
                $creditCardBinObj,
                $installmentSelectedObj
            );

            return [
                'cart' => [
                    'model' => $quote,
                ]
            ];
        } catch (GraphQlInputException | GraphQlNoSuchEntityException | GraphQlAuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical('GraphQL error in ApplyInterest: ' . $e->getMessage());
            throw new GraphQlInputException(__('Error applying interest: %1', $e->getMessage()));
        }
    }
}
