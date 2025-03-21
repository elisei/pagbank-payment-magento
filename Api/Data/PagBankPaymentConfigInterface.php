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

namespace PagBank\PaymentMagento\Api\Data;

/**
 * Interface for PagBank Payment Configuration data.
 *
 * @api
 */
interface PagBankPaymentConfigInterface
{
    /**
     * Get payment method code.
     *
     * @return string
     */
    public function getMethodCode();

    /**
     * Set payment method code.
     *
     * @param string $methodCode
     * @return $this
     */
    public function setMethodCode($methodCode);

    /**
     * Get payment method title.
     *
     * @return string|null
     */
    public function getTitle();

    /**
     * Set payment method title.
     *
     * @param string $title
     * @return $this
     */
    public function setTitle($title);

    /**
     * Check if payment method is active.
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsActive();

    /**
     * Set payment method active status.
     *
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive($isActive);

    /**
     * Get payment method instructions.
     *
     * @return string|null
     */
    public function getInstructions();

    /**
     * Set payment method instructions.
     *
     * @param string $instructions
     * @return $this
     */
    public function setInstructions($instructions);

    /**
     * Get payment method logo details.
     *
     * @return array|null
     */
    public function getLogo();

    /**
     * Set payment method logo details.
     *
     * @param array $logo
     * @return $this
     */
    public function setLogo($logo);

    /**
     * Get additional config options.
     *
     * @return array|null
     */
    public function getAdditionalData();

    /**
     * Set additional config options.
     *
     * @param array $additionalData
     * @return $this
     */
    public function setAdditionalData($additionalData);
}
