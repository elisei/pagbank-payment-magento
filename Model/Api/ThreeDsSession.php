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

use Laminas\Http\ClientFactory;
use Laminas\Http\Request;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use PagBank\PaymentMagento\Api\ThreeDsSessionInterface;
use PagBank\PaymentMagento\Api\Data\ThreeDsSessionDataInterface;
use PagBank\PaymentMagento\Api\Data\ThreeDsSessionDataInterfaceFactory;
use PagBank\PaymentMagento\Gateway\Config\Config as ConfigBase;
use Psr\Log\LoggerInterface;

/**
 * Class 3ds Session - Get Session for Checkout 3ds on PagBank.
 */
class ThreeDsSession implements ThreeDsSessionInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ConfigBase
     */
    protected $configBase;

    /**
     * @var ClientFactory
     */
    protected $httpClientFactory;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var ThreeDsSessionDataInterfaceFactory
     */
    protected $sessionData;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * ThreeDsSession constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param ConfigBase $configBase
     * @param ClientFactory $httpClientFactory
     * @param Json $json
     * @param ThreeDsSessionDataInterfaceFactory $sessionData
     * @param LoggerInterface $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigBase $configBase,
        ClientFactory $httpClientFactory,
        Json $json,
        ThreeDsSessionDataInterfaceFactory $sessionData,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->configBase = $configBase;
        $this->httpClientFactory = $httpClientFactory;
        $this->json = $json;
        $this->sessionData = $sessionData;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getSession(): ThreeDsSessionDataInterface
    {
        try {
            /** @var ThreeDsSessionDataInterface $data */
            $data = $this->sessionData->create();
            
            $session = $this->getSessionInPagBank();

            if (isset($session['session']) && isset($session['expires_at'])) {
                $data->setSessionId((string)$session['session']);
                $data->setExpiresAt((string)$session['expires_at']);
            } else {
                $this->logger->error('Invalid session data returned from PagBank: ' . json_encode($session));
                throw new LocalizedException(__('Unable to create 3DS session.'));
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->critical('Error in ThreeDsSession::getSession: ' . $e->getMessage());
            throw new LocalizedException(__('Error retrieving 3DS session: %1', $e->getMessage()));
        }
    }

    /**
     * Get Session in PagBank
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getSessionInPagBank(): array
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();

            /** @var LaminasClient $client */
            $client = $this->httpClientFactory->create();
            $url = $this->configBase->getApiSDKUrl($storeId);
            $apiConfigs = $this->configBase->getApiConfigs();
            $headers = $this->configBase->getApiHeaders($storeId);
            $uri = $url.'checkout-sdk/sessions';
            
            $client->setUri($uri);
            $client->setHeaders($headers);
            $client->setMethod(Request::METHOD_POST);
            $client->setOptions($apiConfigs);
            $response = $client->send();
            
            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
                $this->logger->error('PagBank API error: ' . $response->getStatusCode() . ' - ' . $response->getReasonPhrase());
                throw new LocalizedException(__('Error communicating with PagBank API.'));
            }
            
            $responseBody = $response->getBody();
            $dataResponse = $this->json->unserialize($responseBody);
            
            if (empty($dataResponse) || !is_array($dataResponse)) {
                $this->logger->error('Invalid response from PagBank API: ' . $responseBody);
                throw new LocalizedException(__('Invalid response from PagBank API.'));
            }

            return $dataResponse;
        } catch (InvalidArgumentException $e) {
            $this->logger->critical('Invalid JSON returned by the gateway: ' . $e->getMessage());
            throw new NoSuchEntityException(__('Invalid JSON was returned by the gateway'));
        } catch (\Exception $e) {
            $this->logger->critical('Error in getSessionInPagBank: ' . $e->getMessage());
            throw new LocalizedException(__('Error communicating with PagBank: %1', $e->getMessage()));
        }
    }
}
