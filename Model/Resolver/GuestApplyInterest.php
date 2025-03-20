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
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterface;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterfaceFactory;
use PagBank\PaymentMagento\Api\Data\InstallmentSelectedInterface;
use PagBank\PaymentMagento\Api\Data\InstallmentSelectedInterfaceFactory;
use PagBank\PaymentMagento\Api\GuestInterestManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * Class GuestApplyInterest Resolver - Calculate and apply interest to guest cart for selected installment.
 */
class GuestApplyInterest implements ResolverInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

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
     * @var GuestInterestManagementInterface
     */
    private $guestInterestManagement;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param CreditCardBinInterfaceFactory $creditCardBinFactory
     * @param InstallmentSelectedInterfaceFactory $installmentSelectedFactory
     * @param GuestInterestManagementInterface $guestInterestManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        CartTotalRepositoryInterface $cartTotalRepository,
        CreditCardBinInterfaceFactory $creditCardBinFactory,
        InstallmentSelectedInterfaceFactory $installmentSelectedFactory,
        GuestInterestManagementInterface $guestInterestManagement,
        LoggerInterface $logger
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->creditCardBinFactory = $creditCardBinFactory;
        $this->installmentSelectedFactory = $installmentSelectedFactory;
        $this->guestInterestManagement = $guestInterestManagement;
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
        $this->logger->debug('GuestApplyInterest resolver called with args: ' . json_encode($args));
        
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('Required parameter "input" is missing or invalid.'));
        }

        $input = $args['input'];

        if (!isset($input['cart_id']) || empty($input['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing or empty.'));
        }

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
            $cartId = $input['cart_id'];
            if (empty($cartId)) {
                throw new GraphQlInputException(__('Invalid cart_id provided.'));
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
            $cartTotals = $this->guestInterestManagement->generatePagBankInterest(
                $cartId,
                $creditCardBinObj,
                $installmentSelectedObj
            );

            // Obter o ID real do carrinho a partir do ID mascarado
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            if (!$quoteIdMask->getQuoteId()) {
                throw new GraphQlNoSuchEntityException(__('Cart with ID "%1" does not exist.', $cartId));
            }
            
            $quoteId = $quoteIdMask->getQuoteId();
            $quote = $this->cartRepository->get($quoteId);

            return [
                'cart' => [
                    'model' => $quote,
                ]
            ];

        } catch (GraphQlInputException | GraphQlNoSuchEntityException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical('GraphQL error in GuestApplyInterest: ' . $e->getMessage());
            throw new GraphQlInputException(__('Error applying interest to guest cart: %1', $e->getMessage()));
        }
    }
}
