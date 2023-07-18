<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Model\Http;

use Exception;
use InvalidArgumentException;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Http\ClientException;
use RuntimeException;

class Service
{
    /**
     * @var string $client_id
     */
    private string $client_id;

    /**
     * @var string $merchant_api_key
     */
    private string $merchant_api_key;

    /**
     * @var Request $httpRequest
     */
    private Request $httpRequest;

    /**
     * @var string $url
     */
    private string $url;

    /**
     * @param Request $httpRequest
     */
    public function __construct(Request $httpRequest)
    {
        $this->httpRequest = $httpRequest;
    }

    /**
     * Get payments and merchant logo
     *
     * @param string $paymentMethod
     * @return array|null
     * @throws ClientException
     */
    public function getValidationServices(string $paymentMethod): array
    {
        $params = [
            'payment_method' => $paymentMethod,
            'channel' => 'magento'
        ];

        $headers = [
            'Authorization: apikey ' . $this->client_id . ':' . $this->merchant_api_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ];

        $this->httpRequest->setHeaders($headers);
        return $this->httpRequest->get('transactions/validation_services', $params);
    }

    /**
     * Completes a checkout transaction using the provided data.
     *
     * @param string $data The data to send in the request, in JSON format.
     * @param string $path The path of the API endpoint to call.
     * @throws RuntimeException If an error occurs while making the request.
     * @throws ClientException If a network error occurs during the request.
     */
    public function checkoutTransaction(string $data, string $path)
    {
        // Validate input data
        if (empty($data) || empty($path)) {
            throw new InvalidArgumentException('Invalid input data');
        }

        // Set the HTTP headers for the request
        $headers = [
            'Authorization: apikey ' . $this->client_id . ':' . $this->merchant_api_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ];

        // Set the headers on the HTTP request object
        $this->httpRequest->setHeaders($headers);

        try {
            // Make the HTTP POST request to the API endpoint
            $response = $this->httpRequest->post($path . '/checkout_transaction', $data);
        } catch (Exception $e) {
            // Handle any exceptions that occur during the request
            throw new ClientException(new Phrase("Error completing checkout transaction: " . $e->getMessage()));
        }

        // Return the response from the API as an array
        return $response;
    }

    /**
     * Get the client ID.
     *
     * @return string
     */
    public function getClientId(): string
    {
        return $this->client_id;
    }

    /**
     * Set the client ID.
     *
     * @param string $client_id
     */
    public function setClientId(string $client_id): void
    {
        $this->client_id = $client_id;
    }

    /**
     * Get the merchant API key.
     *
     * @return string
     */
    public function getMerchantApiKey(): string
    {
        return $this->merchant_api_key;
    }

    /**
     * Set the merchant API key.
     *
     * @param string $merchant_api_key
     */
    public function setMerchantApiKey(string $merchant_api_key): void
    {
        $this->merchant_api_key = $merchant_api_key;
    }

    /**
     * Set base url.
     *
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->httpRequest->setBaseUrl($url);
    }
}
