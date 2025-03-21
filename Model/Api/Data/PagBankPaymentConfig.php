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

namespace PagBank\PaymentMagento\Model\Api\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use PagBank\PaymentMagento\Api\Data\PagBankPaymentConfigInterface;

/**
 * Class PagBankPaymentConfig - Model data.
 */
class PagBankPaymentConfig extends AbstractSimpleObject implements PagBankPaymentConfigInterface
{
    /**
     * Constants for keys of data array.
     */
    private const METHOD_CODE = 'method_code';
    private const TITLE = 'title';
    private const IS_ACTIVE = 'is_active';
    private const INSTRUCTIONS = 'instructions';
    private const LOGO = 'logo';
    private const ADDITIONAL_DATA = 'additional_data';

    /**
     * @inheritdoc
     */
    public function getMethodCode()
    {
        return $this->_get(self::METHOD_CODE);
    }

    /**
     * @inheritdoc
     */
    public function setMethodCode($methodCode)
    {
        return $this->setData(self::METHOD_CODE, $methodCode);
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return $this->_get(self::TITLE);
    }

    /**
     * @inheritdoc
     */
    public function setTitle($title)
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * @inheritdoc
     */
    public function getIsActive()
    {
        return $this->_get(self::IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive($isActive)
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    /**
     * @inheritdoc
     */
    public function getInstructions()
    {
        return $this->_get(self::INSTRUCTIONS);
    }

    /**
     * @inheritdoc
     */
    public function setInstructions($instructions)
    {
        return $this->setData(self::INSTRUCTIONS, $instructions);
    }

    /**
     * @inheritdoc
     */
    public function getLogo()
    {
        return $this->_get(self::LOGO);
    }

    /**
     * @inheritdoc
     */
    public function setLogo($logo)
    {
        return $this->setData(self::LOGO, $logo);
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalData()
    {
        return $this->_get(self::ADDITIONAL_DATA);
    }

    /**
     * @inheritdoc
     */
    public function setAdditionalData($additionalData)
    {
        return $this->setData(self::ADDITIONAL_DATA, $additionalData);
    }
}
