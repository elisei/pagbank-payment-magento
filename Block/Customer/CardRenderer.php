<?php
/**
 * PagBank Payment Magento Module.
 *
 * Copyright © 2023 PagBank. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace PagBank\PaymentMagento\Block\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use PagBank\PaymentMagento\Model\Ui\ConfigProviderBase;

class CardRenderer extends AbstractCardRenderer
{
    public const YEARS_RANGE = 10;

    /**
     * Can render specified token.
     *
     * @param PaymentTokenInterface $token
     *
     * @return bool
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === ConfigProviderBase::METHOD_CODE_CC;
    }

    /**
     * Get Last Numbers.
     *
     * @return string
     */
    public function getNumberLast4Digits(): string
    {
        return $this->getTokenDetails()['cc_last4'];
    }

    /**
     * Get Expiration Date.
     *
     * @return string
     */
    public function getExpDate(): string
    {
        return $this->getTokenDetails()['cc_exp_month'].'/'.$this->getTokenDetails()['cc_exp_year'];
    }

    /**
     * Get Icon Url.
     *
     * @return string
     */
    public function getIconUrl(): string
    {
        return $this->getIconForType($this->getTokenDetails()['cc_type'])['url'];
    }

    /**
     * Get Icon Height.
     *
     * @return int
     */
    public function getIconHeight(): int
    {
        return $this->getIconForType($this->getTokenDetails()['cc_type'])['height'];
    }

    /**
     *  Get Icon Width.
     *
     * @return int
     */
    public function getIconWidth(): int
    {
        return $this->getIconForType($this->getTokenDetails()['cc_type'])['width'];
    }

    /**
     * Retrieve list of months translation
     *
     * @return array
     */
    public function getMonths()
    {
        $data = [];
        for ($index = 1; $index <= 12; $index++) {
            $data[$index] = $index;
        }
        return $data;
    }

    /**
     * Retrieve array of available years
     *
     * @return array
     */
    public function getYears()
    {
        $years = [];
        $first = (int)date("Y");
        for ($index = 0; $index <= self::YEARS_RANGE; $index++) {
            $year = $first + $index;
            $years[$year] = $year;
        }
        return $years;
    }
}
