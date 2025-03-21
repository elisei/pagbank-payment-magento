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

namespace PagBank\PaymentMagento\Api;

/**
 * Interface for managing PagBank payment configurations.
 *
 * @api
 */
interface PagBankPaymentConfigManagerInterface
{
    /**
     * Get all payment method configurations.
     *
     * @param int|null $storeId
     * @return \PagBank\PaymentMagento\Api\Data\PagBankPaymentConfigInterface[]
     */
    public function getPaymentConfigs($storeId = null);

    /**
     * Get payment method configuration by method code.
     *
     * @param string $methodCode
     * @param int|null $storeId
     * @return \PagBank\PaymentMagento\Api\Data\PagBankPaymentConfigInterface
     */
    public function getPaymentConfigByMethod($methodCode, $storeId = null);
}
