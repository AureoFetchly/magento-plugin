<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Model;

use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @codeCoverageIgnore
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    private const KEY_CLIENT_ID = 'client_id';
    private const KEY_MERCHANT_API_KEY = 'merchant_api_key';
    private const KEY_YODLEE_NOTE = 'yodlee_note';
    private const KEY_DATAX_NOTE = 'datax_note';

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var RequestHttp
     */
    protected RequestHttp $request;

    /**
     * @var OrderRepository
     */
    protected OrderRepository $orderRepository;

    /**
     * @var SessionQuote
     */
    protected SessionQuote $sessionQuote;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param RequestHttp $request
     * @param OrderRepository $orderRepository
     * @param SessionQuote $sessionQuote
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface  $scopeConfig,
        StoreManagerInterface $storeManager,
        RequestHttp           $request,
        OrderRepository       $orderRepository,
        SessionQuote          $sessionQuote,
        string                $methodCode = null,
        string                $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->sessionQuote = $sessionQuote;
    }

    /**
     * Get store id from config values
     *
     * @return int|null
     *
     * @throws InputException|NoSuchEntityException
     */
    public function getStoreId(): ?int
    {
        $currentStoreId = null;
        $currentStoreIdInAdmin = $this->sessionQuote->getStoreId();
        if (!$currentStoreIdInAdmin) {
            $currentStoreId = $this->storeManager->getStore()->getId();
        }
        $dataParams = $this->request->getParams();
        if (isset($dataParams['order_id'])) {
            $order = $this->orderRepository->get($dataParams['order_id']);
            if ($order->getEntityId()) {
                return $order->getStoreId();
            }
        }

        return $currentStoreId ?: $currentStoreIdInAdmin;
    }

    /**
     * Get client id
     *
     * @return string|null
     *
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getClientId(): string
    {
        return $this->getValue(self::KEY_CLIENT_ID, $this->getStoreId());
    }

    /**
     * Get notes
     *
     * @return array
     *
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getNotes(): array
    {
        $notes = [];

        $yodleeNote = $this->getValue(self::KEY_YODLEE_NOTE, $this->getStoreId());
        if (!empty($yodleeNote)) {
            $notes['yodlee'] = $yodleeNote;
        }

        $dataxNote = $this->getValue(self::KEY_DATAX_NOTE, $this->getStoreId());
        if (!empty($dataxNote)) {
            $notes['datax'] = $dataxNote;
        }

        return $notes;
    }

    /**
     * Get merchant api key
     *
     * @return string|null
     *
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getMerchantApiKey(): string
    {
        return $this->getValue(self::KEY_MERCHANT_API_KEY, $this->getStoreId());
    }
}
