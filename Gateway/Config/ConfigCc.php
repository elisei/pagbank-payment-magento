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

namespace PagBank\PaymentMagento\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Config\Config as PaymentConfig;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config Cc - Returns form of payment configuration properties for Credit Card.
 */
class ConfigCc extends PaymentConfig
{
    /**
     * @const string
     */
    public const METHOD = 'pagbank_paymentmagento_cc';

    /**
     * @const string
     */
    public const CC_TYPES = 'payment/pagbank_paymentmagento_cc/cctypes';

    /**
     * @const string
     */
    public const CVV_ENABLED = 'cvv_enabled';

    /**
     * @const string
     */
    public const ACTIVE = 'active';

    /**
     * @const string
     */
    public const TITLE = 'title';

    /**
     * @const string
     */
    public const CC_MAPPER = 'cctypes_mapper';

    /**
     * @const string
     */
    public const GET_TAX_ID = 'get_tax_id';

    /**
     * @const string
     */
    public const GET_PHONE = 'get_phone';

    /**
     * @const string
     */
    public const PAYMENT_ACTION = 'payment_action';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json                 $json
     * @param string               $methodCode
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $json,
        $methodCode = self::METHOD
    ) {
        parent::__construct($scopeConfig, $methodCode);
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
    }

    /**
     * Should the cvv field be shown.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isCvvEnabled($storeId = null): bool
    {
        $pathPattern = 'payment/%s/%s';

        return (bool) $this->scopeConfig->getValue(
            sprintf($pathPattern, self::METHOD, self::CVV_ENABLED),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Payment configuration status.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isActive($storeId = null): bool
    {
        $pathPattern = 'payment/%s/%s';

        return (bool) $this->scopeConfig->getValue(
            sprintf($pathPattern, self::METHOD, self::ACTIVE),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get title of payment.
     *
     * @param int|null $storeId
     *
     * @return string|null
     */
    public function getTitle($storeId = null): ?string
    {
        $pathPattern = 'payment/%s/%s';

        return $this->scopeConfig->getValue(
            sprintf($pathPattern, self::METHOD, self::TITLE),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get if document capture on the form.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function hasTaxIdCapture($storeId = null): bool
    {
        $pathPattern = 'payment/%s/%s';

        return (bool) $this->scopeConfig->getValue(
            sprintf($pathPattern, self::METHOD, self::GET_TAX_ID),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get if phone capture on the form.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function hasPhoneCapture($storeId = null): bool
    {
        $pathPattern = 'payment/%s/%s';

        return (bool) $this->scopeConfig->getValue(
            sprintf($pathPattern, self::METHOD, self::GET_PHONE),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Has Capture.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function hasCapture($storeId = null): bool
    {
        $pathPattern = 'payment/%s/%s';
        $typePaymentAction = $this->scopeConfig->getValue(
            sprintf($pathPattern, self::METHOD, self::PAYMENT_ACTION),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($typePaymentAction === AbstractMethod::ACTION_AUTHORIZE) {
            return false;
        }

        return true;
    }

    /**
     * Should the cc types.
     *
     * @param int|null $storeId
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function getCcAvailableTypes($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::CC_TYPES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Cc Mapper.
     *
     * @param int|null $storeId
     *
     * @return array
     */
    public function getCcTypesMapper($storeId = null): array
    {
        $pathPattern = 'payment/%s/%s';

        $ccTypesMapper = $this->scopeConfig->getValue(
            sprintf($pathPattern, self::METHOD, self::CC_MAPPER),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $result = $this->json->unserialize($ccTypesMapper);

        return is_array($result) ? $result : [];
    }

    /**
     * Get Max Installments.
     *
     * @param int|null $storeId
     *
     * @return int
     */
    public function getMaxInstallments($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            'payment/pagbank_paymentmagento_cc/max_installment',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Interest Free.
     *
     * @param int|null $storeId
     *
     * @return int
     */
    public function getInterestFree($storeId = null): int
    {
        $free = (int) $this->scopeConfig->getValue(
            'payment/pagbank_paymentmagento_cc/interest_free',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($free === 1) {
            return 0;
        }

        return $free;
    }

    /**
     * Get Min Value Installments.
     *
     * @param int|null $storeId
     *
     * @return int
     */
    public function getMinValuelInstallment($storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            'payment/pagbank_paymentmagento_cc/min_value_installment',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
