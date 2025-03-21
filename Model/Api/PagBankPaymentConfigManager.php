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

namespace PagBank\PaymentMagento\Model\Api;

use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use PagBank\PaymentMagento\Api\Data\PagBankPaymentConfigInterface;
use PagBank\PaymentMagento\Api\Data\PagBankPaymentConfigInterfaceFactory;
use PagBank\PaymentMagento\Api\PagBankPaymentConfigManagerInterface;
use PagBank\PaymentMagento\Model\Ui\ConfigProviderBase;
use PagBank\PaymentMagento\Model\Ui\ConfigProviderBoleto;
use PagBank\PaymentMagento\Model\Ui\ConfigProviderCc;
use PagBank\PaymentMagento\Model\Ui\ConfigProviderDeepLink;
use PagBank\PaymentMagento\Model\Ui\ConfigProviderPix;
use PagBank\PaymentMagento\Gateway\Config\Config as ConfigBase;
use PagBank\PaymentMagento\Gateway\Config\ConfigBoleto;
use PagBank\PaymentMagento\Gateway\Config\ConfigCc;
use PagBank\PaymentMagento\Gateway\Config\ConfigPix;
use PagBank\PaymentMagento\Gateway\Config\ConfigDeepLink;

/**
 * Class PagBankPaymentConfigManager - Manages PagBank payment configurations.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PagBankPaymentConfigManager implements PagBankPaymentConfigManagerInterface
{
    /**
     * @var PagBankPaymentConfigInterfaceFactory
     */
    private $configFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ConfigProviderCc
     */
    private $configProviderCc;

    /**
     * @var ConfigProviderBoleto
     */
    private $configProviderBoleto;
    
    /**
     * @var ConfigProviderPix
     */
    private $configProviderPix;
    
    /**
     * @var ConfigProviderDeepLink
     */
    private $providerDeepLink;
    
    /**
     * @var ConfigBase
     */
    private $configBase;
    
    /**
     * @var ConfigCc
     */
    private $configCc;
    
    /**
     * @var ConfigBoleto
     */
    private $configBoleto;
    
    /**
     * @var ConfigPix
     */
    private $configPix;
    
    /**
     * @var ConfigDeepLink
     */
    private $configDeepLink;
    
    /**
     * @var Escaper
     */
    private $escaper;
    
    /**
     * @var Json
     */
    private $json;

    /**
     * @param PagBankPaymentConfigInterfaceFactory $configFactory
     * @param StoreManagerInterface $storeManager
     * @param ConfigProviderCc $configProviderCc
     * @param ConfigProviderBoleto $configProviderBoleto
     * @param ConfigProviderPix $configProviderPix
     * @param ConfigProviderDeepLink $providerDeepLink
     * @param ConfigBase $configBase
     * @param ConfigCc $configCc
     * @param ConfigBoleto $configBoleto
     * @param ConfigPix $configPix
     * @param ConfigDeepLink $configDeepLink
     * @param Escaper $escaper
     * @param Json $json
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        PagBankPaymentConfigInterfaceFactory $configFactory,
        StoreManagerInterface $storeManager,
        ConfigProviderCc $configProviderCc,
        ConfigProviderBoleto $configProviderBoleto,
        ConfigProviderPix $configProviderPix,
        ConfigProviderDeepLink $providerDeepLink,
        ConfigBase $configBase,
        ConfigCc $configCc,
        ConfigBoleto $configBoleto,
        ConfigPix $configPix,
        ConfigDeepLink $configDeepLink,
        Escaper $escaper,
        Json $json
    ) {
        $this->configFactory = $configFactory;
        $this->storeManager = $storeManager;
        $this->configProviderCc = $configProviderCc;
        $this->configProviderBoleto = $configProviderBoleto;
        $this->configProviderPix = $configProviderPix;
        $this->providerDeepLink = $providerDeepLink;
        $this->configBase = $configBase;
        $this->configCc = $configCc;
        $this->configBoleto = $configBoleto;
        $this->configPix = $configPix;
        $this->configDeepLink = $configDeepLink;
        $this->escaper = $escaper;
        $this->json = $json;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentConfigs($storeId = null)
    {
        if ($storeId === null) {
            try {
                $storeId = (int)$this->storeManager->getStore()->getId();
            } catch (NoSuchEntityException $e) {
                $storeId = null;
            }
        }

        $result = [];

        $methodCodes = [
            ConfigProviderCc::CODE,
            ConfigProviderBoleto::CODE,
            ConfigProviderPix::CODE,
            ConfigProviderDeepLink::CODE
        ];

        foreach ($methodCodes as $methodCode) {
            $config = $this->getPaymentConfigByMethod($methodCode, $storeId);
            if ($config->getIsActive()) {
                $result[] = $config;
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentConfigByMethod($methodCode, $storeId = null)
    {
        /** @var \PagBank\PaymentMagento\Api\Data\PagBankPaymentConfigInterface $config */
        $config = $this->configFactory->create();
        $config->setMethodCode($methodCode);

        switch ($methodCode) {
            case ConfigProviderCc::CODE:
                $this->setCcConfig($config, $storeId);
                break;
            case ConfigProviderBoleto::CODE:
                $this->setBoletoConfig($config, $storeId);
                break;
            case ConfigProviderPix::CODE:
                $this->setPixConfig($config, $storeId);
                break;
            case ConfigProviderDeepLink::CODE:
                $this->setDeepLinkConfig($config, $storeId);
                break;
            default:
                throw new NoSuchEntityException(__('Payment method %1 does not exist.', $methodCode));
        }

        return $config;
    }

    /**
     * Configure Credit Card method
     *
     * @param PagBankPaymentConfigInterface $config
     * @param int|null $storeId
     * @return void
     */
    private function setCcConfig(PagBankPaymentConfigInterface $config, $storeId = null)
    {
        $isActive = $this->configCc->isActive($storeId);
        $config->setIsActive($isActive);
        $config->setTitle($this->configCc->getTitle($storeId));

        $ccConfig = $this->configProviderCc->getConfig();
        $paymentMethod = $ccConfig['payment'][ConfigCc::METHOD] ?? [];
        
        $ccTypesMapper = $this->configCc->getCcTypesMapper($storeId);
        $formattedTypesMapper = [];
        foreach ($ccTypesMapper as $code => $name) {
            $formattedTypesMapper[] = [
                'code' => $code,
                'name' => $name
            ];
        }
        
        $threeDsConfig = [
            'enable' => $this->configCc->hasThreeDsAuth($storeId),
            'enable_deb' => $this->configCc->isActiveDebit($storeId),
            'reject' => $this->configCc->hasRejectNotAuth($storeId),
            'env' => $this->configCc->getThreeDsEnv($storeId),
            'max_try_place' => $this->configCc->getMaxTryPlaceOrder($storeId),
            'instruction' => nl2br(
                $this->escaper->escapeHtml(
                    $this->configCc->getInstructionForThreeDs($storeId),
                    ['b']
                )
            ),
        ];
        
        $additionalData = [
            'useCvv' => $this->configCc->isCvvEnabled($storeId),
            'ccTypesMapper' => $formattedTypesMapper,
            'icons' => $this->formatIconsForGraphQL($paymentMethod['icons'] ?? []),
            'tax_id_capture' => $this->configCc->hasTaxIdCapture($storeId),
            'phone_capture' => $this->configCc->hasPhoneCapture($storeId),
            'public_key' => $this->configBase->getMerchantGatewayPublicKey($storeId),
            'ccVaultCode' => ConfigProviderCc::VAULT_CODE,
            'threeDs' => $threeDsConfig
        ];
        
        $config->setAdditionalData($additionalData);
        $config->setLogo($paymentMethod['logo'] ?? []);
    }

    /**
     * Configure Boleto method
     *
     * @param PagBankPaymentConfigInterface $config
     * @param int|null $storeId
     * @return void
     */
    private function setBoletoConfig(PagBankPaymentConfigInterface $config, $storeId = null)
    {
        $isActive = $this->configBoleto->isActive($storeId);
        $config->setIsActive($isActive);
        $config->setTitle($this->configBoleto->getTitle($storeId));
        
        $boletoConfig = $this->configProviderBoleto->getConfig();
        $paymentMethod = $boletoConfig['payment'][ConfigBoleto::METHOD] ?? [];
        
        $config->setInstructions(nl2br(
            $this->escaper->escapeHtml(
                $this->configBoleto->getInstructionCheckout($storeId),
                ['b']
            )
        ));
        
        $additionalData = [
            'name_capture' => $this->configBoleto->hasNameCapture($storeId),
            'tax_id_capture' => $this->configBoleto->hasTaxIdCapture($storeId),
            'expiration' => $this->configBoleto->getExpirationFormat($storeId)
        ];
        
        $config->setAdditionalData($additionalData);
        $config->setLogo($paymentMethod['logo'] ?? []);
    }

    /**
     * Configure Pix method
     *
     * @param PagBankPaymentConfigInterface $config
     * @param int|null $storeId
     * @return void
     */
    private function setPixConfig(PagBankPaymentConfigInterface $config, $storeId = null)
    {
        $isActive = $this->configPix->isActive($storeId);
        $config->setIsActive($isActive);
        $config->setTitle($this->configPix->getTitle($storeId));
        
        $pixConfig = $this->configProviderPix->getConfig();
        $paymentMethod = $pixConfig['payment'][ConfigProviderPix::CODE] ?? [];
        
        $text = $this->configPix->getInstructionCheckout($storeId);
        $time = $this->configPix->getTextTime($storeId);
        $replaceText = __($text, $time);
        
        $config->setInstructions(nl2br($this->escaper->escapeHtml(
            $replaceText,
            ['b']
        )));
        
        $additionalData = [
            'name_capture' => $this->configPix->hasNameCapture($storeId),
            'tax_id_capture' => $this->configPix->hasTaxIdCapture($storeId),
            'phone_capture' => $this->configPix->hasPhoneCapture($storeId),
            'expiration' => $this->configPix->getExpiration($storeId)
        ];
        
        $config->setAdditionalData($additionalData);
        $config->setLogo($paymentMethod['logo'] ?? []);
    }

    /**
     * Configure DeepLink method
     *
     * @param PagBankPaymentConfigInterface $config
     * @param int|null $storeId
     * @return void
     */
    private function setDeepLinkConfig(PagBankPaymentConfigInterface $config, $storeId = null)
    {
        $isActive = $this->configDeepLink->isActive($storeId);
        $config->setIsActive($isActive);
        $config->setTitle($this->configDeepLink->getTitle($storeId));
        
        $deepLinkConfig = $this->providerDeepLink->getConfig();
        $paymentMethod = $deepLinkConfig['payment'][ConfigProviderDeepLink::CODE] ?? [];
        
        $text = $this->configDeepLink->getInstructionCheckout($storeId);
        
        $config->setInstructions(nl2br($this->escaper->escapeHtml(
            $text,
            ['b', 'a', 'h3', 'target']
        )));
        
        $additionalData = [
            'name_capture' => $this->configDeepLink->hasNameCapture($storeId),
            'tax_id_capture' => $this->configDeepLink->hasTaxIdCapture($storeId),
            'phone_capture' => $this->configDeepLink->hasPhoneCapture($storeId)
        ];
        
        $config->setAdditionalData($additionalData);
        $config->setLogo($paymentMethod['logo'] ?? []);
    }

    /**
     * Format icons data for GraphQL output
     *
     * @param array $icons
     * @return array
     */
    private function formatIconsForGraphQL(array $icons): array
    {
        $formattedIcons = [];
        foreach ($icons as $code => $iconData) {
            if (isset($iconData['url'])) {
                $iconData['code'] = $code;
                $formattedIcons[] = $iconData;
            }
        }
        return $formattedIcons;
    }
}
