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
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PagBank\PaymentMagento\Api\Data\CardTypeTransactionInterface;
use PagBank\PaymentMagento\Api\Data\CardTypeTransactionInterfaceFactory;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterface;
use PagBank\PaymentMagento\Api\Data\CreditCardBinInterfaceFactory;
use PagBank\PaymentMagento\Api\GuestListInstallmentsManagementInterface;

/**
 * Class GuestListInstallments Resolver - Get available installments for credit card for guest cart.
 */
class GuestListInstallments implements ResolverInterface
{
    /**
     * @var CreditCardBinInterfaceFactory
     */
    private $creditCardBinFactory;

    /**
     * @var CardTypeTransactionInterfaceFactory
     */
    private $cardTypeTransaction;

    /**
     * @var GuestListInstallmentsManagementInterface
     */
    private $guestListInstall;

    /**
     * @param CreditCardBinInterfaceFactory $creditCardBinFactory
     * @param CardTypeTransactionInterfaceFactory $cardTypeTransaction
     * @param GuestListInstallmentsManagementInterface $guestListInstall
     */
    public function __construct(
        CreditCardBinInterfaceFactory $creditCardBinFactory,
        CardTypeTransactionInterfaceFactory $cardTypeTransaction,
        GuestListInstallmentsManagementInterface $guestListInstall
    ) {
        $this->creditCardBinFactory = $creditCardBinFactory;
        $this->cardTypeTransaction = $cardTypeTransaction;
        $this->guestListInstall = $guestListInstall;
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
        
        $this->validateCartId($input);
        $this->validateCreditCardBin($input);

        try {
            $cartId = $input['cart_id'];
            $creditCardBin = $this->createCreditCardBinObject($input);
            $cardTypeTransaction = $this->createCardTypeTransactionObject($input);

            $installmentList = $this->guestListInstall->generateListInstallments(
                $cartId,
                $creditCardBin,
                $cardTypeTransaction
            );

            return $this->formatInstallmentList($installmentList);
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Error retrieving installment options: %1', $e->getMessage()));
        }
    }

    /**
     * Validate input parameter
     *
     * @param array|null $args
     * @return void
     * @throws GraphQlInputException
     */
    private function validateInput(?array $args): void
    {
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__(
                'Required parameter "input" is missing.'
            ));
        }
    }

    /**
     * Validate cart_id parameter
     *
     * @param array $input
     * @return void
     * @throws GraphQlInputException
     */
    private function validateCartId(array $input): void
    {
        if (!isset($input['cart_id']) || empty($input['cart_id'])) {
            throw new GraphQlInputException(__(
                'Required parameter "cart_id" is missing or empty.'
            ));
        }
    }

    /**
     * Validate credit_card_bin parameter
     *
     * @param array $input
     * @return void
     * @throws GraphQlInputException
     */
    private function validateCreditCardBin(array $input): void
    {
        if (!isset($input['credit_card_bin']) || empty($input['credit_card_bin']['credit_card_bin'])) {
            throw new GraphQlInputException(__(
                'Required parameter "credit_card_bin" is missing or empty.'
            ));
        }
        
        $creditCardBin = $input['credit_card_bin']['credit_card_bin'];
        if (!preg_match('/^\d+$/', $creditCardBin)) {
            throw new GraphQlInputException(__(
                'Invalid credit_card_bin format. Must contain only digits.'
            ));
        }
    }

    /**
     * Create CreditCardBin object
     *
     * @param array $input
     * @return CreditCardBinInterface
     */
    private function createCreditCardBinObject(array $input): CreditCardBinInterface
    {
        /** @var CreditCardBinInterface $creditCardBin */
        $creditCardBin = $this->creditCardBinFactory->create();
        $creditCardBin->setCreditCardBin($input['credit_card_bin']['credit_card_bin']);
        
        return $creditCardBin;
    }

    /**
     * Create CardTypeTransaction object if present in input
     *
     * @param array $input
     * @return CardTypeTransactionInterface|null
     */
    private function createCardTypeTransactionObject(array $input): ?CardTypeTransactionInterface
    {
        if (!isset($input['card_type_transaction']) || 
            !isset($input['card_type_transaction']['card_type_transaction']) || 
            empty($input['card_type_transaction']['card_type_transaction'])) {
            return null;
        }
        
        /** @var CardTypeTransactionInterface $cardTypeTransaction */
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
