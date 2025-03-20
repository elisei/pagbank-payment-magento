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
 * Plugin for preparing PagBank Credit Card Vault payment data
 */
class SetCreditCardVaultPaymentData
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
     * @throws GraphQlInputException
     */
    public function afterExecute(
        SetPaymentMethodOnCart $subject,
        $result,
        Quote $quote,
        array $paymentData
    ) {
        if ($paymentData['code'] !== ConfigProviderCc::VAULT_CODE) {
            return $result;
        }

        try {
            $payment = $quote->getPayment();
            
            $inputData = $paymentData[ConfigProviderCc::VAULT_CODE] ?? [];
            
            if (empty($inputData['public_hash'])) {
                throw new GraphQlInputException(__('Required parameter "public_hash" is missing.'));
            }
            
            $payment->setAdditionalInformation('public_hash', $inputData['public_hash']);
            
            $fieldMapping = [
                'cc_installments' => DataAssignCcObserver::PAYMENT_INFO_CC_INSTALLMENTS,
                'payer_tax_id' => DataAssignCcObserver::PAYMENT_INFO_PAYER_TAX_ID,
                'payer_phone' => DataAssignCcObserver::PAYMENT_INFO_PAYER_PHONE,
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
