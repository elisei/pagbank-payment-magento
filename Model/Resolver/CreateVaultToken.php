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
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PagBank\PaymentMagento\Api\PagBankVaultManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * Class CreateVaultToken - Create vault token for customer credit card.
 */
class CreateVaultToken implements ResolverInterface
{
    /**
     * @var PagBankVaultManagementInterface
     */
    private $pagBankVaultManagement;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PagBankVaultManagementInterface $pagBankVaultManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        PagBankVaultManagementInterface $pagBankVaultManagement,
        LoggerInterface $logger
    ) {
        $this->pagBankVaultManagement = $pagBankVaultManagement;
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
        $this->logger->debug('CreateVaultToken resolver called with args: ' . json_encode($args));
        
        // Verificar autenticação
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('Required parameter "input" is missing or invalid.'));
        }

        $input = $args['input'];
        if (!isset($input['encrypted_card']) || empty($input['encrypted_card'])) {
            throw new GraphQlInputException(__('Required parameter "encrypted_card" is missing or empty.'));
        }

        try {
            $customerId = (int)$context->getUserId();
            $encryptedCard = $input['encrypted_card'];

            $vaultToken = $this->pagBankVaultManagement->createVaultToken(
                $customerId,
                $encryptedCard
            );

            if (!$vaultToken || !$vaultToken->getPagBankToken()) {
                throw new GraphQlInputException(__('Failed to create vault token.'));
            }

            return [
                'pagbank_token' => $vaultToken->getPagBankToken(),
                'public_hash' => $vaultToken->getPublicHash(),
                'card_brand' => $vaultToken->getCardBrand(),
                'last_digits' => $vaultToken->getLastDigits(),
                'expiration_date' => $vaultToken->getExpirationDate(),
                'created_at' => $vaultToken->getCreatedAt(),
                'is_active' => $vaultToken->getIsActive(),
                'website_id' => $vaultToken->getWebsiteId()
            ];
        } catch (GraphQlInputException | GraphQlAuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical('GraphQL error in CreateVaultToken: ' . $e->getMessage());
            throw new GraphQlInputException(__('Error creating vault token: %1', $e->getMessage()));
        }
    }
}
