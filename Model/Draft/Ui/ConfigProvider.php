<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Model\Draft\Ui;

use Edebit\Payment\Model\Adminhtml\Source\Environment;
use Edebit\Payment\Model\Config as SampleConfig;
use Edebit\Payment\Model\Http\Service;
use ErrorException;
use Exception;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;

/**
 * @codeCoverageIgnore
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
    public const METHOD_CODE = 'draft';
    private const METHOD_KEY_ACTIVE = 'payment/draft/active';
    private const CONFIG_STORE_NAME = 'general/store_information/name';
    private const CONFIG_STORE_URL = 'web/unsecure/base_url';
    private const METHOD_KEY_ENV = 'payment/draft/environment';

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var SampleConfig $sampleConfig
     */
    private SampleConfig $sampleConfig;

    /**
     * @var Repository $assetRepo
     */
    private Repository $assetRepo;

    /**
     * @var Service $httpService
     */
    private Service $httpService;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SampleConfig $sampleConfig
     * @param Repository $assetRepo
     * @param Service $httpService
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SampleConfig         $sampleConfig,
        Repository           $assetRepo,
        Service $httpService
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->sampleConfig = $sampleConfig;
        $this->assetRepo = $assetRepo;
        $this->httpService = $httpService;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     * @throws NoSuchEntityException|InputException
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                self::METHOD_CODE => [
                    'isEnabled' => $this->isEnabled(),
                    'storeName' => $this->getStoreName(),
                    'clientInfo' => $this->getValidationServices(),
                    'baseUrl' => $this->getBaseUrl(),
                    'imageUrl' => $this->assetRepo->getUrl("Edebit_Payment::images/draft.png"),
                    'notes' => $this->sampleConfig->getNotes()
                ]
            ]
        ];
    }

    /**
     * Get Payment configuration status
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::METHOD_KEY_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get url
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        $environment = $this->scopeConfig->getValue(self::METHOD_KEY_ENV, ScopeInterface::SCOPE_STORE);
    
        if ($environment === Environment::ENVIRONMENT_LOCAL) {
            return 'https://edebit.development.fetchlydev.com/api/v1/';
        } elseif ($environment === Environment::ENVIRONMENT_SANDBOX) {
            return 'https://edebit.staging.fetchlydev.com/api/v1/';
        } else {
            return 'https://app.edebitdirect.com//api/v1/';
        }
    }

    /**
     * Get store name. If store name is empty, use the base URL
     *
     * @return string
     */
    private function getStoreName(): string
    {
        $storeName = $this->scopeConfig->getValue(
            self::CONFIG_STORE_NAME,
            ScopeInterface::SCOPE_STORE
        );

        if (!$storeName) {
            $storeName = $this->scopeConfig->getValue(
                self::CONFIG_STORE_URL,
                ScopeInterface::SCOPE_STORE
            );
        }
        return $storeName;
    }

    /**
     * Get client token
     *
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     * @todo handle api errors
     */
    private function getValidationServices(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $clientId = $this->sampleConfig->getClientId();
        $merchantApiKey = $this->sampleConfig->getMerchantApiKey();

        if (empty($clientId) || empty($merchantApiKey)) {
            throw new ErrorException('Something is missing');
        }

        $this->httpService->setUrl($this->getBaseUrl());
        $this->httpService->setClientId($clientId);
        $this->httpService->setMerchantApiKey($merchantApiKey);

        $merchant = $this->httpService->getValidationServices('DRAFT');
        $merchant['client_id'] = $clientId;
        $merchant['merchant_key'] = $merchantApiKey;

        return $merchant;
    }
}
