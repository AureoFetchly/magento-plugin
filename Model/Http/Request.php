<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Model\Http;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Http\ClientException;

class Request
{
    /**
     * @var string $base_url
     */
    private string $base_url;

    /**
     * @var Curl $curl
     */
    private Curl $curl;

    /**
     * @var array $headers
     */
    private array $headers;

    /**
     */
    public function __construct()
    {
        $this->curl = new Curl();
    }

    /**
     * Set headers
     *
     * @param array $headers
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * Set base url
     *
     * @param string $base_url
     */
    public function setBaseUrl(string $base_url): void
    {
        $this->base_url = $base_url;
    }

    /**
     * Get request
     *
     * @param string $path
     * @param array $params
     * @return mixed
     * @throws ClientException
     */
    public function get(string $path, array $params = [])
    {
        $url = $this->base_url . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->sendRequest('GET', $url);
        return json_decode($response, true);
    }

    /**
     * Post Request
     *
     * @param string $path
     * @param string $data
     * @return mixed
     * @throws ClientException
     */
    public function post(string $path, string $data)
    {
        $url = $this->base_url . $path;
        $response = $this->sendRequest('POST', $url, $data);
        return json_decode($response, true);
    }

    /**
     * Send http request
     *
     * @param string $method
     * @param string $url
     * @param string|null $data
     * @return string
     * @throws ClientException
     */
    private function sendRequest(string $method, string $url, string $data = null): string
    {
        foreach ($this->headers as $header) {
            list($key, $value) = explode(': ', $header);
            $this->curl->addHeader($key, $value);
        }

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);

        switch ($method) {
            case 'GET':
                $this->curl->get($url);
                break;
            case 'POST':
                $this->curl->post($url, $data);
                break;
            default:
                throw new ClientException(new Phrase("Invalid method: $method"));
        }

        return $this->curl->getBody();
    }
}
