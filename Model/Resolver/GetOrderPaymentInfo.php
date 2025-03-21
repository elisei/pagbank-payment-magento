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

namespace PagBank\PaymentMagento\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\Order\Payment;

/**
 * Class GetOrderPaymentInfo Resolver - Retrieves payment information for a PagBank order.
 */
class GetOrderPaymentInfo implements ResolverInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteria;


    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteria
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteria,
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteria = $searchCriteria;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|\Magento\Framework\GraphQl\Query\Resolver\Value|mixed
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$context->getUserId() && empty($args['input']['guest_email'])) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('Required parameter "input" is missing or invalid.'));
        }

        $input = $args['input'];
        
        if (empty($input['order_id']) && empty($input['increment_id'])) {
            throw new GraphQlInputException(__('Required parameter "order_id" or "increment_id" is missing.'));
        }

        try {
            $order = null;
            
            if (!empty($input['order_id'])) {
                $order = $this->orderRepository->get((int)$input['order_id']);
            } elseif (!empty($input['increment_id'])) {
                $searchCriteria = $this->searchCriteria
                    ->addFilter('increment_id', $input['increment_id'])
                    ->create();
                $orders = $this->orderRepository->getList($searchCriteria)->getItems();
                
                if (empty($orders)) {
                    throw new GraphQlNoSuchEntityException(__('Order with increment ID "%1" does not exist.', $input['increment_id']));
                }
                
                $order = reset($orders);
            }

            if (!$order || !$order->getEntityId()) {
                throw new GraphQlNoSuchEntityException(__('Order does not exist.'));
            }

            if ($context->getUserId() && (int)$context->getUserId() !== (int)$order->getCustomerId()) {
                throw new GraphQlAuthorizationException(__('The current customer does not have access to this order.'));
            }

            if (!$context->getUserId() && !empty($input['guest_email'])) {
                if ($input['guest_email'] !== $order->getCustomerEmail()) {
                    throw new GraphQlAuthorizationException(__('The provided email does not match the order email.'));
                }
            }

            $payment = $order->getPayment();
            if (!$payment) {
                throw new GraphQlNoSuchEntityException(__('Payment information not found for this order.'));
            }
            
            $method = $payment->getMethod();
            if (!str_contains($method, 'pagbank_paymentmagento')) {
                throw new GraphQlNoSuchEntityException(__('Order was not processed with PagBank.'));
            }

            return $this->formatPaymentDetails($order, $payment);
            
        } catch (GraphQlNoSuchEntityException | GraphQlAuthorizationException | GraphQlInputException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Error retrieving payment information: %1', $e->getMessage()));
        }
    }

    /**
     * Format payment details based on payment method
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param Payment $payment
     * @return array
     */
    private function formatPaymentDetails($order, $payment)
    {
        $method = $payment->getMethod();
        $additionalInfo = $payment->getAdditionalInformation();
        $result = [
            'order_id' => $order->getEntityId(),
            'increment_id' => $order->getIncrementId(),
            'payment_method' => $method,
            'payment_status' => $order->getStatus(),
            'created_at' => $order->getCreatedAt(),
            'grand_total' => $order->getGrandTotal(),
            'currency_code' => $order->getOrderCurrencyCode(),
            'pagbank_order_id' => $payment->getLastTransId(),
            'additional_information' => []
        ];

        $result['additional_information'] = $this->getCommonAdditionalInfo($additionalInfo);

        switch ($method) {
            case 'pagbank_paymentmagento_cc':
            case 'pagbank_paymentmagento_cc_vault':
                $result = array_merge($result, $this->getCreditCardInfo($additionalInfo));
                break;
            case 'pagbank_paymentmagento_boleto':
                $result = array_merge($result, $this->getBoletoInfo($additionalInfo));
                break;
            case 'pagbank_paymentmagento_pix':
                $result = array_merge($result, $this->getPixInfo($additionalInfo));
                break;
            case 'pagbank_paymentmagento_deep_link':
                $result = array_merge($result, $this->getDeepLinkInfo($additionalInfo));
                break;
            default:
                break;
        }

        return $result;
    }

    /**
     * Get common additional information for all payment methods
     *
     * @param array $additionalInfo
     * @return array
     */
    private function getCommonAdditionalInfo($additionalInfo)
    {
        $commonInfo = [];
        
        $commonFields = [
            'payer_name', 
            'payer_tax_id', 
            'payer_phone'
        ];
        
        foreach ($commonFields as $field) {
            if (isset($additionalInfo[$field])) {
                $commonInfo[$field] = $additionalInfo[$field];
            }
        }
        
        return $commonInfo;
    }

    /**
     * Get credit card specific payment information
     *
     * @param array $additionalInfo
     * @return array
     */
    private function getCreditCardInfo($additionalInfo)
    {
        $ccInfo = [
            'payment_type' => 'credit_card',
            'credit_card_details' => []
        ];

        $ccFields = [
            'pagbank_interest_amount', 
            'cc_type', 
            'cc_number', 
            'cc_exp_month',
            'cc_exp_year',
            'cc_installments',
            'cc_cardholder_name',
            'cc_authorization_code',
            'cc_nsu',
            'three_ds_auth_status'
        ];

        foreach ($ccFields as $field) {
            if (isset($additionalInfo[$field])) {
                $ccInfo['credit_card_details'][$field] = $additionalInfo[$field];
            }
        }

        return $ccInfo;
    }

    /**
     * Get boleto specific payment information
     *
     * @param array $additionalInfo
     * @return array
     */
    private function getBoletoInfo($additionalInfo)
    {
        $boletoInfo = [
            'payment_type' => 'boleto',
            'boleto_details' => []
        ];

        $boletoFields = [
            'boleto_line_code',
            'boleto_pdf_href',
            'expiration_date'
        ];

        foreach ($boletoFields as $field) {
            if (isset($additionalInfo[$field])) {
                $boletoInfo['boleto_details'][$field] = $additionalInfo[$field];
            }
        }

        return $boletoInfo;
    }

    /**
     * Get PIX specific payment information
     *
     * @param array $additionalInfo
     * @return array
     */
    private function getPixInfo($additionalInfo)
    {
        $pixInfo = [
            'payment_type' => 'pix',
            'pix_details' => []
        ];

        $pixFields = [
            'qr_code_image', 
            'qr_code', 
            'expiration_date'
        ];

        foreach ($pixFields as $field) {
            if (isset($additionalInfo[$field])) {
                $pixInfo['pix_details'][$field] = $additionalInfo[$field];
            }
        }

        return $pixInfo;
    }

    /**
     * Get DeepLink specific payment information
     *
     * @param array $additionalInfo
     * @return array
     */
    private function getDeepLinkInfo($additionalInfo)
    {
        $deepLinkInfo = [
            'payment_type' => 'deep_link',
            'deep_link_details' => []
        ];

        $deepLinkFields = [
            'qr_code_url_image',
            'deep_link_url',
            'expiration_date'
        ];

        foreach ($deepLinkFields as $field) {
            if (isset($additionalInfo[$field])) {
                $deepLinkInfo['deep_link_details'][$field] = $additionalInfo[$field];
            }
        }

        return $deepLinkInfo;
    }
}
