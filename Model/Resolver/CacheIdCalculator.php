<?php
declare(strict_types=1);

namespace PagBank\PaymentMagento\Model\Resolver;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class CacheIdCalculator implements IdentityInterface
{
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