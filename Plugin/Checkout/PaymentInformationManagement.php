<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Plugin\Checkout;

use Closure;
use Edebit\Payment\Model\MethodList;
use Exception;
use Magento\Checkout\Model\PaymentInformationManagement as CheckoutPaymentInformationManagement;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\CartManagementInterface;
use Psr\Log\LoggerInterface;

class PaymentInformationManagement
{
    /**
     * @var CartManagementInterface
     */
    private CartManagementInterface $cartManagement;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var bool
     */
    private bool $checkMethods;

    /**
     * @var MethodList
     */
    private MethodList $methodList;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var Http
     */
    protected Http $response;

    /**
     * GuestPaymentInformationManagement constructor.
     * @param CartManagementInterface $cartManagement
     * @param LoggerInterface $logger
     * @param MethodList $methodList
     * @param ManagerInterface $messageManager
     * @param UrlInterface $urlBuilder
     * @param Http $response
     * @param bool $checkMethods
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        LoggerInterface              $logger,
        MethodList                   $methodList,
        ManagerInterface             $messageManager,
        UrlInterface                 $urlBuilder,
        Http                         $response,
        bool                         $checkMethods = false
    ) {
        $this->cartManagement = $cartManagement;
        $this->logger = $logger;
        $this->checkMethods = $checkMethods;
        $this->methodList = $methodList;
        $this->messageManager = $messageManager;
        $this->urlBuilder = $urlBuilder;
        $this->response = $response;
    }

    /**
     * Intercepts original method call, saves payment information, and places order via cart management service.
     *
     * @param CheckoutPaymentInformationManagement $subject
     * @param Closure $proceed
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return int|mixed|null
     * @throws ClientException
     * @throws CouldNotSaveException
     * @throws Exception
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        CheckoutPaymentInformationManagement $subject,
        Closure                              $proceed,
        string                               $cartId,
        PaymentInterface                     $paymentMethod,
        AddressInterface                     $billingAddress = null
    ): mixed {
        if ($this->checkMethods && !in_array($paymentMethod->getMethod(), $this->methodList->get())) {
            return $proceed($cartId, $paymentMethod, $billingAddress);
        }
        $subject->savePaymentInformation($cartId, $paymentMethod, $billingAddress);
        try {
            $response = $this->cartManagement->placeOrder($cartId);
        } catch (ClientException $e) {
            $this->logger->error('Payment capturing error: ' . $e->getMessage());
            throw $e;
        } catch (LocalizedException $e) {
            $this->logger->error('Payment capturing error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__($e->getMessage()));
            $url = $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => false]);
            return $this->response->setRedirect($url)->sendResponse();
        } catch (Exception $e) {
            $this->logger->error('Payment capturing error: ' . $e->getMessage());
            throw new Exception(__('An error occurred while capturing the payment. Please try again.'));
        }
        return $response;
    }
}
