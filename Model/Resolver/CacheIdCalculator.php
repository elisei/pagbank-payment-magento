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

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

/**
 * Provides cache identities for GraphQL payment config queries
 */
class CacheIdCalculator implements IdentityInterface
{
    private $cacheTag;

    /**
     * Constructor
     *
     * @param string $cacheTag
     */
    public function __construct(string $cacheTag)
    {
        $this->cacheTag = $cacheTag;
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(array $resolvedData): array
    {
        $ids = [];

        if (isset($resolvedData['items'])) {
            $ids[] = $this->cacheTag;
            
            foreach ($resolvedData['items'] as $paymentConfig) {
                if (isset($paymentConfig['method_code'])) {
                    $ids[] = sprintf('%s_%s', $this->cacheTag, $paymentConfig['method_code']);
                }
            }
        }

        return $ids;
    }
}
