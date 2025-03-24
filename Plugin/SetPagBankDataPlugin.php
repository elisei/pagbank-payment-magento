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

namespace PagBank\PaymentMagento\Plugin;

use Magento\QuoteGraphQl\Model\Cart\SetPaymentMethodOnCart as SetPaymentMethodOnCartModel;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class SetPagBankDataPlugin - Process data for PagBank payment methods.
 */
class SetPagBankDataPlugin
{
    /**
     * Payer data field names
     */
    public const PAYER_NAME = 'payer_name';
    public const PAYER_TAX_ID = 'payer_tax_id';
    public const PAYER_PHONE = 'payer_phone';

    /**
     * Credit Card specific fields
     */
    public const CC_NUMBER_TOKEN = 'cc_number_token';
    public const CC_INSTALLMENTS = 'cc_installments';
    public const CC_CARDHOLDER_NAME = 'cc_cardholder_name';
    public const CC_CID = 'cc_cid';
    public const CARD_TYPE_TRANSACTION = 'card_type_transaction';
    public const THREE_DS_SESSION = 'three_ds_session';
    public const THREE_DS_AUTH = 'three_ds_auth';
    public const THREE_DS_AUTH_STATUS = 'three_ds_auth_status';
    public const IS_ACTIVE_PAYMENT_TOKEN_ENABLER = 'is_active_payment_token_enabler';

    /**
     * Credit Card Vault specific fields
     */
    public const PUBLIC_HASH = 'public_hash';

    /**
     * PagBank method code prefix
     */
    public const METHOD_PREFIX = 'pagbank_paymentmagento_';

    /**
     * PagBank payment methods
     */
    public const SUPPORTED_METHODS = [
        'cc',
        'cc_vault',
        'boleto',
        'pix',
        'deep_link'
    ];

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CartRepositoryInterface $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Around Set Payment Method
     *
     * @param SetPaymentMethodOnCartModel $subject
     * @param callable $proceed
     * @param Quote $cart
     * @param array $paymentData
     * @return PaymentInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        SetPaymentMethodOnCartModel $subject,
        callable $proceed,
        $cart,
        $paymentData
    ) {
        $methodCode = $paymentData['code'] ?? '';

        if (!$this->isSupported($methodCode)) {
            return $proceed($cart, $paymentData);
        }

        $payment = $proceed($cart, $paymentData);
        
        $shortCode = str_replace(self::METHOD_PREFIX, '', $methodCode);
        
        $pagBankMethodData = $paymentData[$paymentData['code']] ?? [];
        
        if (empty($pagBankMethodData)) {
            return $payment;
        }

        $paymentObject = $cart->getPayment();
        $paymentObject->unsAdditionalInformation();

        switch ($shortCode) {
            case 'cc':
                $this->processCreditCardData($paymentObject, $pagBankMethodData);
                break;
            case 'cc_vault':
                $this->processCreditCardVaultData($paymentObject, $pagBankMethodData);
                break;
            case 'boleto':
            case 'pix':
            case 'deep_link':
                $this->processPayerData($paymentObject, $pagBankMethodData);
                break;
        }

        $this->cartRepository->save($cart);
        
        return $payment;
    }

    /**
     * Processa dados específicos do cartão de crédito
     *
     * @param PaymentInterface $payment
     * @param array $ccData
     * @return void
     */
    private function processCreditCardData($payment, array $ccData): void
    {
        $ccFields = [
            self::CC_NUMBER_TOKEN,
            self::CC_INSTALLMENTS,
            self::CC_CARDHOLDER_NAME,
            self::CC_CID,
            self::CARD_TYPE_TRANSACTION,
            self::THREE_DS_SESSION,
            self::THREE_DS_AUTH,
            self::THREE_DS_AUTH_STATUS,
            self::IS_ACTIVE_PAYMENT_TOKEN_ENABLER
        ];
        
        foreach ($ccFields as $field) {
            if (isset($ccData[$field])) {
                $payment->setAdditionalInformation($field, $ccData[$field]);
            }
        }
        
        $this->processPayerData($payment, $ccData);
    }

    /**
     * Processa dados específicos do cartão salvo (vault)
     *
     * @param PaymentInterface $payment
     * @param array $vaultData
     * @return void
     */
    private function processCreditCardVaultData($payment, array $vaultData): void
    {
        if (isset($vaultData[self::PUBLIC_HASH])) {
            $payment->setAdditionalInformation(self::PUBLIC_HASH, $vaultData[self::PUBLIC_HASH]);
        }
        
        $vaultFields = [
            self::CC_INSTALLMENTS,
            self::CARD_TYPE_TRANSACTION,
            self::THREE_DS_SESSION,
            self::THREE_DS_AUTH,
            self::THREE_DS_AUTH_STATUS
        ];
        
        foreach ($vaultFields as $field) {
            if (isset($vaultData[$field])) {
                $payment->setAdditionalInformation($field, $vaultData[$field]);
            }
        }
        
        $this->processPayerData($payment, $vaultData);
    }

    /**
     * Processa dados comuns do pagador para todos os métodos
     *
     * @param PaymentInterface $payment
     * @param array $payerData
     * @return void
     */
    private function processPayerData($payment, array $payerData): void
    {
        if (isset($payerData[self::PAYER_NAME])) {
            $payment->setAdditionalInformation(self::PAYER_NAME, $payerData[self::PAYER_NAME]);
        }
        
        if (isset($payerData[self::PAYER_TAX_ID])) {
            $payment->setAdditionalInformation(self::PAYER_TAX_ID, $payerData[self::PAYER_TAX_ID]);
        }
        
        if (isset($payerData[self::PAYER_PHONE])) {
            $payment->setAdditionalInformation(self::PAYER_PHONE, $payerData[self::PAYER_PHONE]);
        }
    }

    /**
     * Verifica se o método é suportado pelo plugin
     *
     * @param string $methodCode
     * @return bool
     */
    private function isSupported(string $methodCode): bool
    {
        foreach (self::SUPPORTED_METHODS as $supportedMethod) {
            if ($methodCode === self::METHOD_PREFIX . $supportedMethod) {
                return true;
            }
        }
        
        return false;
    }
}
