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

/**
 * Class ApplyInterest Resolver - Calculate and apply interest to cart for selected installment.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyInterest implements ResolverInterface
{
    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuote;

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
    private $installmentSelected;

    /**
     * @var InterestManagementInterface
     */
    private $interestManagement;

    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuote
     * @param CartRepositoryInterface $cartRepository
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param CreditCardBinInterfaceFactory $creditCardBinFactory
     * @param InstallmentSelectedInterfaceFactory $installmentSelected
     * @param InterestManagementInterface $interestManagement
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuote,
        CartRepositoryInterface $cartRepository,
        CartTotalRepositoryInterface $cartTotalRepository,
        CreditCardBinInterfaceFactory $creditCardBinFactory,
        InstallmentSelectedInterfaceFactory $installmentSelected,
        InterestManagementInterface $interestManagement
    ) {
        $this->maskedQuote = $maskedQuote;
        $this->cartRepository = $cartRepository;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->creditCardBinFactory = $creditCardBinFactory;
        $this->installmentSelected = $installmentSelected;
        $this->interestManagement = $interestManagement;
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $this->validateUserAuthorization($context);
        $this->validateInput($args);

        try {
            $input = $args['input'];
            $cartId = $this->getCartId($input, $context);
            $quote = $this->getValidQuote($cartId);
            
            $creditCardBin = $this->validateAndCreateCreditCardBin($input);
            $installmentSelected = $this->validateAndCreateInstallmentSelected($input);

            $this->interestManagement->generatePagBankInterest(
                (int)$cartId,
                $creditCardBin,
                $installmentSelected
            );

            return [
                'cart' => [
                    'model' => $quote,
                ]
            ];
        } catch (GraphQlInputException | GraphQlNoSuchEntityException | GraphQlAuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Error applying interest: %1', $e->getMessage()));
        }
    }

    /**
     * Validate user authorization
     *
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @return void
     * @throws GraphQlAuthorizationException
     */
    private function validateUserAuthorization($context): void
    {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }
    }

    /**
     * Validate input parameters
     *
     * @param array|null $args
     * @return void
     * @throws GraphQlInputException
     */
    private function validateInput(?array $args): void
    {
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('Required parameter "input" is missing or invalid.'));
        }

        $input = $args['input'];
        $this->validateCreditCardBinInput($input);
        $this->validateInstallmentSelectedInput($input);
    }

    /**
     * Validate credit card bin input
     *
     * @param array $input
     * @return void
     * @throws GraphQlInputException
     */
    private function validateCreditCardBinInput(array $input): void
    {
        if (!isset($input['credit_card_bin']) 
            || !isset($input['credit_card_bin']['credit_card_bin']) 
            || empty($input['credit_card_bin']['credit_card_bin'])
        ) {
            throw new GraphQlInputException(__('Required parameter "credit_card_bin" is missing or empty.'));
        }
    }

    /**
     * Validate installment selected input
     *
     * @param array $input
     * @return void
     * @throws GraphQlInputException
     */
    private function validateInstallmentSelectedInput(array $input): void
    {
        if (!isset($input['installment_selected']) 
            || !isset($input['installment_selected']['installment_selected']) 
            || empty($input['installment_selected']['installment_selected'])
        ) {
            throw new GraphQlInputException(__('Required parameter "installment_selected" is missing or empty.'));
        }
    }

    /**
     * Get and validate cart ID
     *
     * @param array $input
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @return string
     * @throws GraphQlInputException
     */
    private function getCartId(array $input, $context): string
    {
        $cartId = $input['cart_id'] ?? null;
        
        if (!$cartId) {
            return (string)$context->getUserId();
        }
        
        try {
            return (string)$this->maskedQuote->execute($cartId);
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Could not find a cart with the provided cart_id.'));
        }
    }

    /**
     * Get and validate quote
     *
     * @param string $cartId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws GraphQlNoSuchEntityException
     * @throws GraphQlInputException
     */
    private function getValidQuote(string $cartId)
    {
        try {
            $quote = $this->cartRepository->get((int)$cartId);
            if (!$quote->getId()) {
                throw new GraphQlNoSuchEntityException(__('Cart with ID "%1" does not exist.', $cartId));
            }
            
            if (!$quote->getItemsCount()) {
                throw new GraphQlInputException(__('Cart is empty.'));
            }

            return $quote;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('Cart with ID "%1" does not exist.', $cartId));
        }
    }

    /**
     * Validate and create credit card bin object
     *
     * @param array $input
     * @return CreditCardBinInterface
     * @throws GraphQlInputException
     */
    private function validateAndCreateCreditCardBin(array $input): CreditCardBinInterface
    {
        $creditCardBin = $input['credit_card_bin']['credit_card_bin'];
        if (!preg_match('/^\d+$/', $creditCardBin)) {
            throw new GraphQlInputException(__('Invalid credit_card_bin format. Must contain only digits.'));
        }

        /** @var CreditCardBinInterface $creditCardBinObj */
        $creditCardBinObj = $this->creditCardBinFactory->create();
        $creditCardBinObj->setCreditCardBin($creditCardBin);

        return $creditCardBinObj;
    }

    /**
     * Validate and create installment selected object
     *
     * @param array $input
     * @return InstallmentSelectedInterface
     * @throws GraphQlInputException
     */
    private function validateAndCreateInstallmentSelected(array $input): InstallmentSelectedInterface
    {
        $installmentSelected = (int)$input['installment_selected']['installment_selected'];
        if ($installmentSelected <= 0) {
            throw new GraphQlInputException(__('Invalid installment number. Must be a positive integer.'));
        }

        /** @var InstallmentSelectedInterface $insSelectedObj */
        $insSelectedObj = $this->installmentSelected->create();
        $insSelectedObj->setInstallmentSelected($installmentSelected);

        return $insSelectedObj;
    }
}
