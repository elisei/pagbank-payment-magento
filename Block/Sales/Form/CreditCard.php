<?php
/**
 * PagBank Payment Magento Module.
 *
 * Copyright © 2023 PagBank. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace PagBank\PaymentMagento\Block\Sales\Form;

use Magento\Framework\View\Element\Template\Context;
use PagBank\PaymentMagento\Gateway\Config\Config;

/**
 * Class CreditCard - Form for payment by Credit Card.
 */
class CreditCard extends \Magento\Payment\Block\Form
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Context   $context
     * @param Config    $config
     */
    public function __construct(
        Context $context,
        Config $config
    ) {
        parent::__construct($context);
        $this->config = $config;
    }

    /**
     * Get Public Key
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->config->getMerchantGatewayPublicKey();
    }
}
