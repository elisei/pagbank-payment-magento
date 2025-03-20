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

use Exception;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\SetPaymentMethodOnCart;
use PagBank\PaymentMagento\Model\Ui\ConfigProviderCc;
use PagBank\PaymentMagento\Observer\DataAssignCcObserver;

/**
 * Plugin for preparing PagBank Credit Card payment data
 */
class SetCreditCardPaymentData
{
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
     * After setting payment method, manually add additional data to payment
     *
     * @param SetPaymentMethodOnCart $subject
     * @param mixed $result
     * @param Quote $quote
     * @param array $paymentData
     * @return mixed
     */
    public function afterExecute(
        SetPaymentMethodOnCart $subject,
        $result,
        Quote $quote,
        array $paymentData
    ) {
        if ($paymentData['code'] !== ConfigProviderCc::CODE) {
            return $result;
        }

        try {
            $payment = $quote->getPayment();
            
            $inputData = $paymentData[ConfigProviderCc::CODE] ?? [];
            
            // Map GraphQL input fields to expected additional_data format
            $fieldMapping = [
                'cc_number_token' => DataAssignCcObserver::PAYMENT_INFO_NUMBER_TOKEN,
                'cc_installments' => DataAssignCcObserver::PAYMENT_INFO_CC_INSTALLMENTS,
                'cc_cardholder_name' => DataAssignCcObserver::PAYMENT_INFO_CARDHOLDER_NAME,
                'payer_tax_id' => DataAssignCcObserver::PAYMENT_INFO_PAYER_TAX_ID,
                'payer_phone' => DataAssignCcObserver::PAYMENT_INFO_PAYER_PHONE,
                'is_active_payment_token_enabler' => DataAssignCcObserver::PAYMENT_INFO_CC_SAVE,
                'cc_cid' => DataAssignCcObserver::PAYMENT_INFO_CC_CID,
                'card_type_transaction' => DataAssignCcObserver::PAYMENT_INFO_TYPE_CARD,
                'three_ds_session' => DataAssignCcObserver::PAYMENT_INFO_THREE_DS_SESSION,
                'three_ds_auth' => DataAssignCcObserver::PAYMENT_INFO_THREE_DS_AUTH,
                'three_ds_auth_status' => DataAssignCcObserver::PAYMENT_INFO_THREE_DS_AUTH_STATUS
            ];
            
            foreach ($fieldMapping as $graphQlField => $additionalDataField) {
                if (isset($inputData[$graphQlField])) {
                    $payment->setAdditionalInformation(
                        $additionalDataField,
                        $inputData[$graphQlField]
                    );
                }
            }

            $this->cartRepository->save($quote);

        } catch (GraphQlInputException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new GraphQlInputException(__('Error processing vault payment information: %1', $e->getMessage()));
        }
        
        return $result;
    }
}
