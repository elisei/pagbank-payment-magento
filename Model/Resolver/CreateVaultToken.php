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

/**
 * Class CreateVaultToken - Create vault token for customer credit card.
 */
class CreateVaultToken implements ResolverInterface
{
    /**
     * @var PagBankVaultManagementInterface
     */
    private $pagBankVault;

    /**
     * @param PagBankVaultManagementInterface $pagBankVault
     */
    public function __construct(
        PagBankVaultManagementInterface $pagBankVault
    ) {
        $this->pagBankVault = $pagBankVault;
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
        $this->validateUserAuthorization($context);
        $this->validateInput($args);
        
        try {
            $input = $args['input'];
            $customerId = (int)$context->getUserId();
            $encryptedCard = $input['encrypted_card'];

            $vaultToken = $this->createAndValidateVaultToken($customerId, $encryptedCard);

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
            throw new GraphQlInputException(__('Error creating vault token: %1', $e->getMessage()));
        }
    }

    /**
     * Validate user authorization
     * 
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @return void
     * @throws GraphQlAuthorizationException
     */
    private function validateUserAuthorization($context): void
    {
        if (!$context->getUserId()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }
    }

    /**
     * Validate input parameters
     * 
     * @param array|null $args
     * @return void
     * @throws GraphQlInputException
     */
    private function validateInput(?array $args): void
    {
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('Required parameter "input" is missing or invalid.'));
        }

        $input = $args['input'];
        if (!isset($input['encrypted_card']) || empty($input['encrypted_card'])) {
            throw new GraphQlInputException(__('Required parameter "encrypted_card" is missing or empty.'));
        }
    }

    /**
     * Create and validate vault token
     * 
     * @param int $customerId
     * @param string $encryptedCard
     * @return \PagBank\PaymentMagento\Api\Data\VaultTokenInterface
     * @throws GraphQlInputException
     */
    private function createAndValidateVaultToken(int $customerId, string $encryptedCard)
    {
        $vaultToken = $this->pagBankVault->createVaultToken(
            $customerId,
            $encryptedCard
        );

        if (!$vaultToken || !$vaultToken->getPagBankToken()) {
            throw new GraphQlInputException(__('Failed to create vault token.'));
        }

        return $vaultToken;
    }
}
