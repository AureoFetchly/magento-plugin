<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Gateway\Request;

use Exception;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Edebit\Payment\Observer\DataAssignObserver;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $paymentDO = $buildSubject['payment'];
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        $address = $order->getShippingAddress();

        $additionalInformation = $payment->getAdditionalInformation(
            DataAssignObserver::PAYMENT_METHOD_NONCE
        );

        $achPaymentMethodNonce = json_decode($additionalInformation, true);

        $result = [
            'customer_name' => $address->getFirstname() . ' ' . $address->getLastname(),
            'customer_street' => $address->getStreetLine1(),
            'customer_city' => $address->getCity(),
            'customer_state' => $address->getRegionCode(),
            'customer_zip' => $address->getPostcode(),
            'customer_phone' => $address->getTelephone(),
            'customer_email' => $address->getEmail(),
            'amount' => $order->getGrandTotalAmount(),
            'payment_method' => $achPaymentMethodNonce['payment_method'],
            'channel' => 'magento'
        ];

        return array_merge($result, $achPaymentMethodNonce);
    }
}
