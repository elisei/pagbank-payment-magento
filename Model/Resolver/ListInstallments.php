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
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PagBank\PaymentMagento\Api\Data\CardTypeTransactionInterface;
use PagBank\PaymentMagento\Api\Data\CardTypeTransactionInterfaceFactory;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterface;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterfaceFactory;
use PagBank\PaymentMagento\Api\ListInstallmentsManagementInterface;

/**
 * Class ListInstallments Resolver - Get available installments for credit card.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ListInstallments implements ResolverInterface
{
    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteId;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CreditCardBinInterfaceFactory
     */
    private $creditCardBinFactory;

    /**
     * @var CardTypeTransactionInterfaceFactory
     */
    private $cardTypeTransaction;

    /**
     * @var ListInstallmentsManagementInterface
     */
    private $listInstallments;

    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteId
     * @param CartRepositoryInterface $cartRepository
     * @param CreditCardBinInterfaceFactory $creditCardBinFactory
     * @param CardTypeTransactionInterfaceFactory $cardTypeTransaction
     * @param ListInstallmentsManagementInterface $listInstallments
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteId,
        CartRepositoryInterface $cartRepository,
        CreditCardBinInterfaceFactory $creditCardBinFactory,
        CardTypeTransactionInterfaceFactory $cardTypeTransaction,
        ListInstallmentsManagementInterface $listInstallments
    ) {
        $this->maskedQuoteId = $maskedQuoteId;
        $this->cartRepository = $cartRepository;
        $this->creditCardBinFactory = $creditCardBinFactory;
        $this->cardTypeTransaction = $cardTypeTransaction;
        $this->listInstallments = $listInstallments;
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
        $this->validateInput($args);
        $input = $args['input'];
        
        // Validate cart ID or use customer ID
        $cartId = $this->getCartId($context, $input);
        
        // Validate credit card bin
        $creditCardBin = $this->validateAndCreateCreditCardBin($input);
        
        try {
            // Get and validate quote
            $this->getValidQuote($cartId);
            
            // Create card type transaction if provided
            $cardTypeTransaction = $this->createCardTypeTransaction($input);
            
            // Generate installment list
            $installmentList = $this->listInstallments->generateListInstallments(
                (int)$cartId,
                $creditCardBin,
                $cardTypeTransaction
            );
            
            // Format the response
            return $this->formatInstallmentList($installmentList);
            
        } catch (GraphQlInputException | GraphQlNoSuchEntityException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Error retrieving installment options: %1', $e->getMessage()));
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
    }
    
    /**
     * Get cart ID from context or input
     *
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param array $input
     * @return string
     * @throws GraphQlInputException
     */
    private function getCartId($context, array $input): string
    {
        $cartId = $input['cart_id'] ?? null;
        
        if (!$context->getUserId() && empty($cartId)) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing for guest cart.'));
        }
        
        if (!$cartId) {
            return (string)$context->getUserId();
        }
        
        try {
            return (string)$this->maskedQuoteId->execute($cartId);
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Could not find a cart with the provided cart_id.'));
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
        if (!isset($input['credit_card_bin']) 
            || !isset($input['credit_card_bin']['credit_card_bin']) 
            || empty($input['credit_card_bin']['credit_card_bin'])
        ) {
            throw new GraphQlInputException(__('Required parameter "credit_card_bin" is missing or empty.'));
        }
        
        $creditCardBin = $input['credit_card_bin']['credit_card_bin'];
        if (!preg_match('/^\d+$/', $creditCardBin)) {
            throw new GraphQlInputException(__('Invalid credit_card_bin format. Must contain only digits.'));
        }
        
        $creditCardBinObj = $this->creditCardBinFactory->create();
        $creditCardBinObj->setCreditCardBin($creditCardBin);
        
        return $creditCardBinObj;
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
     * Create CardTypeTransaction object if present in input
     *
     * @param array $input
     * @return CardTypeTransactionInterface|null
     */
    private function createCardTypeTransaction(array $input): ?CardTypeTransactionInterface
    {
        if (!isset($input['card_type_transaction']) || 
            !isset($input['card_type_transaction']['card_type_transaction']) || 
            empty($input['card_type_transaction']['card_type_transaction'])) {
            return null;
        }
        
        $cardTypeTransaction = $this->cardTypeTransaction->create();
        $cardTypeTransaction->setCardTypeTransaction($input['card_type_transaction']['card_type_transaction']);
        
        return $cardTypeTransaction;
    }
    
    /**
     * Format installment list for GraphQL response
     *
     * @param array $installmentList
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function formatInstallmentList($installmentList): array
    {
        if (!is_array($installmentList)) {
            return [];
        }
        
        $formattedList = [];
        foreach ($installmentList as $installment) {
            $item = [
                'installments' => isset($installment['installments']) ? (int)$installment['installments'] : 1,
                'installment_value' => isset($installment['installment_value']) ? (int)$installment['installment_value'] : 0,
                'interest_free' => isset($installment['interest_free']) ? (bool)$installment['interest_free'] : true,
                'amount' => [
                    'value' => isset($installment['amount']['value']) ? (int)$installment['amount']['value'] : 0
                ]
            ];
            
            if (isset($installment['amount']['fees']) && isset($installment['amount']['fees']['buyer']) && 
                isset($installment['amount']['fees']['buyer']['interest']) && 
                isset($installment['amount']['fees']['buyer']['interest']['total'])) {
                $item['amount']['fees'] = [
                    'buyer' => [
                        'interest' => [
                            'total' => (int)$installment['amount']['fees']['buyer']['interest']['total']
                        ]
                    ]
                ];
            }
            
            $formattedList[] = $item;
        }
        
        return $formattedList;
    }
}
