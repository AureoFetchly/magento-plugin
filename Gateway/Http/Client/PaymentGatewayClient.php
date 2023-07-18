<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Gateway\Http\Client;

use Edebit\Payment\Gateway\Response\TxnIdHandler;
use Edebit\Payment\Model\Http\Service as HttpService;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;

class PaymentGatewayClient implements ClientInterface
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var Logger
     */
    protected Logger $customLogger;

    /**
     * @var HttpService $httpService
     */
    private HttpService $httpService;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param Logger $customLogger
     * @param HttpService $httpService
     */
    public function __construct(LoggerInterface $logger, Logger $customLogger, HttpService $httpService)
    {
        $this->logger = $logger;
        $this->customLogger = $customLogger;
        $this->httpService = $httpService;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws LocalizedException
     * @throws Exception
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $body = $transferObject->getBody();
        $this->httpService->setUrl($body['base_url']);
        $this->httpService->setClientId($body['client_id']);
        $this->httpService->setMerchantApiKey($body['merchant_key']);

        try {
            $response = $this->httpService->checkoutTransaction(json_encode($body), $body['gateway']);

            if (isset($response['errors'])) {
                $errorMessage = 'Payment failed: ' . $response['errors'][0];
                throw new ClientException(__($errorMessage));
            }

            if (isset($response['error'])) {
                $errorMessage = 'Payment failed: ' . $response['error'];
                throw new LocalizedException(__($errorMessage));
            }

            $response[TxnIdHandler::TXN_ID] = $this->generateTransactionId();
            $this->customLogger->debug(['response' => $response]);

            return $response;

        } catch (LocalizedException|ClientException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new Exception(__('An error occurred while capturing the payment. Please try again.'));
        }
    }

    /**
     * Generates a transaction ID
     *
     * @return string
     * @throws Exception
     */
    private function generateTransactionId(): string
    {
        return md5(random_int(0, 1000));
    }
}
