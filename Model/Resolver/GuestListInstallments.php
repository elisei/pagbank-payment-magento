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
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__(
                'Required parameter "input" is missing.'
            ));
        }

        $input = $args['input'];

        if (!isset($input['cart_id']) || empty($input['cart_id'])) {
            throw new GraphQlInputException(__(
                'Required parameter "cart_id" is missing or empty.'
            ));
        }

        if (!isset($input['credit_card_bin']) || empty($input['credit_card_bin']['credit_card_bin'])) {
            throw new GraphQlInputException(__(
                'Required parameter "credit_card_bin" is missing or empty.'
            ));
        }

        try {
            $cartId = $input['cart_id'];

            /** @var CreditCardBinInterface $creditCardBin */
            $creditCardBin = $this->creditCardBinFactory->create();
            $creditCardBin->setCreditCardBin($input['credit_card_bin']['credit_card_bin']);

            /** @var CardTypeTransactionInterface|null $cardTypeTransaction */
            $cardTypeTransaction = null;
            if (isset($input['card_type_transaction']) && 
                isset($input['card_type_transaction']['card_type_transaction']) && 
                !empty($input['card_type_transaction']['card_type_transaction'])) {
                $cardTypeTransaction = $this->cardTypeTransaction->create();
                $cardTypeTransaction->setCardTypeTransaction($input['card_type_transaction']['card_type_transaction']);
            }

            $installmentList = $this->guestListInstall->generateListInstallments(
                $cartId,
                $creditCardBin,
                $cardTypeTransaction
            );

            return $installmentList;
        } catch (\Exception $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }
    }
}
