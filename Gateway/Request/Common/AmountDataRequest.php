<?php
/**
 * PagBank Payment Magento Module.
 *
 * Copyright © 2023 PagBank. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace PagBank\PaymentMagento\Gateway\Request\Common;

use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use PagBank\PaymentMagento\Gateway\Config\Config;
use PagBank\PaymentMagento\Gateway\Request\ChargesDataRequest;

/**
 * Class Amount Data Request - Structure of payment amounts for Credit Card and Boleto.
 */
class AmountDataRequest implements BuilderInterface
{
    /**
     * Amount block name.
     */
    public const AMOUNT = 'amount';

    /**
     * Amount Value block name.
     */
    public const AMOUNT_VALUE = 'value';

    /**
     * Amount Value block name.
     */
    public const AMOUNT_CURRENCY = 'currency';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Build.
     *
     * @param array $buildSubject
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function build(array $buildSubject)
    {
        $result = [];

        /** @var PaymentDataObject $paymentDO * */
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /** @var \Magento\Sales\Model\Order $order * */
        $order = $paymentDO->getOrder();

        $grandTotal = $order->getGrandTotalAmount();

        $result[ChargesDataRequest::CHARGES][] = [
            self::AMOUNT => [
                self::AMOUNT_VALUE      => $this->config->formatPrice($grandTotal),
                self::AMOUNT_CURRENCY   => $order->getCurrencyCode(),
            ],
        ];

        return $result;
    }
}
