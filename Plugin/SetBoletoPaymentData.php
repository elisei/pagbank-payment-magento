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
use PagBank\PaymentMagento\Model\Ui\ConfigProviderBoleto;
use PagBank\PaymentMagento\Observer\DataAssignPayerDataObserver;

/**
 * Plugin for preparing PagBank Boleto payment data
 */
class SetBoletoPaymentData
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SetPaymentMethodOnCart $subject,
        $result,
        Quote $quote,
        array $paymentData
    ) {
        if ($paymentData['code'] !== ConfigProviderBoleto::CODE) {
            return $result;
        }

        try {
            $payment = $quote->getPayment();
            
            $inputData = $paymentData[ConfigProviderBoleto::CODE] ?? [];
            
            if (empty($inputData['payer_tax_id'])) {
                throw new GraphQlInputException(__('Required parameter "payer_tax_id" is missing or empty.'));
            }
            
            $fieldMapping = [
                'payer_name' => DataAssignPayerDataObserver::PAYMENT_INFO_PAYER_NAME,
                'payer_tax_id' => DataAssignPayerDataObserver::PAYMENT_INFO_PAYER_TAX_ID,
                'payer_phone' => DataAssignPayerDataObserver::PAYMENT_INFO_PAYER_PHONE
            ];
            
            $payment->unsAdditionalInformation();
            
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
            throw new GraphQlInputException(__('Error processing boleto payment information: %1', $e->getMessage()));
        }
        
        return $result;
    }
}
