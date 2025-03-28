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
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\StoreRepositoryInterface;
use PagBank\PaymentMagento\Api\PagBankPaymentConfigManagerInterface;

/**
 * Class GetPaymentConfigs Resolver - Retrieves payment configurations for PagBank methods.
 */
class GetPaymentConfigs implements ResolverInterface, IdentityInterface
{
    /**
     * @var PagBankPaymentConfigManagerInterface
     */
    private $configManager;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @param PagBankPaymentConfigManagerInterface $configManager
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        PagBankPaymentConfigManagerInterface $configManager,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->configManager = $configManager;
        $this->storeRepository = $storeRepository;
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
        $storeId = null;

        if (isset($args['input']['store_id'])) {
            try {
                $store = $this->storeRepository->getById($args['input']['store_id']);
                $storeId = (int) $store->getId();
            } catch (\Exception $e) {
                throw new GraphQlInputException(__('The store with ID "%1" does not exist.', $args['input']['store_id']));
            }
        }

        try {
            $methodCode = $args['input']['method_code'] ?? null;
            
            if ($methodCode) {
                $config = $this->configManager->getPaymentConfigByMethod($methodCode, $storeId);
                return [
                    'items' => $config->getIsActive() ? [$this->formatConfig($config)] : []
                ];
            } 
            
            if (!$methodCode) {
                $configs = $this->configManager->getPaymentConfigs($storeId);
                $formattedConfigs = [];
                
                foreach ($configs as $config) {
                    $formattedConfigs[] = $this->formatConfig($config);
                }
                
                return [
                    'items' => $formattedConfigs
                ];
            }
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('Error retrieving payment configurations: %1', $e->getMessage()));
        }
    }

    /**
     * Format payment configuration for GraphQL output
     *
     * @param \PagBank\PaymentMagento\Api\Data\PagBankPaymentConfigInterface $config
     * @return array
     */
    private function formatConfig($config)
    {
        $logo = $config->getLogo();
        $additionalData = $config->getAdditionalData();
        
        $result = [
            'method_code' => $config->getMethodCode(),
            'title' => $config->getTitle(),
            'is_active' => $config->getIsActive(),
            'instructions' => $config->getInstructions()
        ];
        
        if (is_array($logo) && !empty($logo)) {
            $result['logo'] = $logo;
        }
        
        if (is_array($additionalData) && !empty($additionalData)) {
            $result['additional_data'] = $additionalData;
        }
        
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(array $resolvedData): array
    {
        $identities = ['pagbank_payment_config'];
        
        if (isset($resolvedData['items']) && is_array($resolvedData['items'])) {
            foreach ($resolvedData['items'] as $config) {
                if (isset($config['method_code'])) {
                    $identities[] = 'pagbank_payment_config_' . $config['method_code'];
                }
            }
        }
        
        return $identities;
    }
}
