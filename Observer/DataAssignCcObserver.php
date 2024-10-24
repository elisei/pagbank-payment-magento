<?php
/**
 * PagBank Payment Magento Module.
 *
 * Copyright © 2023 PagBank. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace PagBank\PaymentMagento\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class Data Assign Cc Observer - Capture credit card payment information.
 */
class DataAssignCcObserver extends AbstractDataAssignObserver
{
    /**
     * @const string
     */
    public const PAYMENT_INFO_NUMBER_TOKEN = 'cc_number_token';

    /**
     * @const string
     */
    public const PAYMENT_INFO_CC_INSTALLMENTS = 'cc_installments';

    /**
     * @const string
     */
    public const PAYMENT_INFO_CARDHOLDER_NAME = 'cc_cardholder_name';

    /**
     * @const string
     */
    public const PAYMENT_INFO_PAYER_TAX_ID = 'payer_tax_id';

    /**
     * @const string
     */
    public const PAYMENT_INFO_PAYER_PHONE = 'payer_phone';

    /**
     * @const string
     */
    public const PAYMENT_INFO_CC_SAVE = 'is_active_payment_token_enabler';

    /**
     * @const string
     */
    public const PAYMENT_INFO_CC_CID = 'cc_cid';

    /**
     * @const string
     */
    public const PAYMENT_INFO_TYPE_CARD = 'card_type_transaction';

    /**
     * @const string
     */
    public const PAYMENT_INFO_THREE_DS_SESSION = 'three_ds_session';

    /**
     * @const string
     */
    public const PAYMENT_INFO_THREE_DS_AUTH = 'three_ds_auth';

    /**
     * @const string
     */
    public const PAYMENT_INFO_THREE_DS_AUTH_STATUS = 'three_ds_auth_status';

    /**
     * @var array
     */
    protected $addInformationList = [
        self::PAYMENT_INFO_NUMBER_TOKEN,
        self::PAYMENT_INFO_CARDHOLDER_NAME,
        self::PAYMENT_INFO_CC_INSTALLMENTS,
        self::PAYMENT_INFO_CC_SAVE,
        self::PAYMENT_INFO_CC_CID,
        self::PAYMENT_INFO_PAYER_TAX_ID,
        self::PAYMENT_INFO_PAYER_PHONE,
        self::PAYMENT_INFO_TYPE_CARD,
        self::PAYMENT_INFO_THREE_DS_SESSION,
        self::PAYMENT_INFO_THREE_DS_AUTH,
        self::PAYMENT_INFO_THREE_DS_AUTH_STATUS
    ];

    /**
     * Execute.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->addInformationList as $addInformationKey) {
            if (isset($additionalData[$addInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $addInformationKey,
                    ($additionalData[$addInformationKey]) ?: null
                );
            }
        }
    }
}
