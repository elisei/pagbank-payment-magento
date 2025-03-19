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
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PagBank\PaymentMagento\Api\ThreeDsSessionInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ThreeDsSession Resolver - Get 3DS Session for PagBank.
 */
class ThreeDsSession implements ResolverInterface
{
    /**
     * @var ThreeDsSessionInterface
     */
    private $threeDsSession;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ThreeDsSessionInterface $threeDsSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        ThreeDsSessionInterface $threeDsSession,
        LoggerInterface $logger
    ) {
        $this->threeDsSession = $threeDsSession;
        $this->logger = $logger;
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
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        try {
            $sessionData = $this->threeDsSession->getSession();
            
            // Verificar se os dados da sessão são válidos
            if (!$sessionData || !$sessionData->getSessionId()) {
                throw new GraphQlInputException(__('Unable to create 3DS session.'));
            }

            return [
                'session_id' => (string)$sessionData->getSessionId(),
                'expires_at' => (string)$sessionData->getExpiresAt()
            ];
        } catch (\Exception $e) {
            $this->logger->critical('GraphQL error in ThreeDsSession: ' . $e->getMessage());
            throw new GraphQlInputException(__('Error retrieving 3DS session: %1', $e->getMessage()));
        }
    }
}
