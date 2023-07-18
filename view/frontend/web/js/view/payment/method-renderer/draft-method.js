/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    "Magento_Checkout/js/view/payment/default",
    "ko",
    "payment-utils",
    "observables",
], function (Component, ko, utils, sharedObservables) {
    "use strict";

    const PAYMENT = "DRAFT";

    return Component.extend({
        defaults: {
            template: "Edebit_Payment/payment/draft",
            redirectAfterPlaceOrder: true,
        },

        initialize: function () {
            const self = this;
            self._super();

            const { client_id, merchant_key, avatar } = self.getMerchantInfo();

            // Observables
            self.paymentUtils = utils(self.getEnvironment(), client_id, merchant_key, PAYMENT);
            self.paymentImage = ko.observable(self.draftImage());
            self.merchantLogo = ko.observable(avatar);
            self.container = "container-fastlink-draft";
            self.isChase = ko.observable(false);
            self.gateways = ko.observableArray(
                self.getMerchantInfo()["validation_services"]
            );
            self.isOtpOptional = ko.observable(
                self.getMerchantInfo()['otp_optional']
            )
            sharedObservables(self);
        },

        yesButton: async function () {
            const self = this;
            self.selectedGateway("datax");
            self.isChase(false);
            await self.requestAccountsToken();
            self.showPaymentModal();
        },

        noButton: async function () {
            const self = this;
            self.selectedGateway("yodlee");
            self.isChase(false);
            await self.requestAccountsToken();
            self.showPaymentModal();
        },

        getCode: function () {
            return "draft";
        },

        /**
         * Returns payment data for the DRAFT payment method.
         * @return {Object} Payment data.
         */
        getData: function () {
            const {
                accountNumber,
                routingNumber,
                emailOrPhone,
                selectedGateway,
                selectedAccount,
                saveBankAccount
            } = this;

            // Get merchant info from config
            const { client_id, merchant_key } = this.getMerchantInfo();

            // Default DRAFT payment method data object
            const draftPaymentMethodData = {
                customer_email_or_phone: this.getEmailOrPhone(emailOrPhone()),
                gateway: selectedGateway(),
                payment_method: PAYMENT,
                client_id,
                merchant_key,
                base_url: this.getEnvironment(),
                save_bank_account: saveBankAccount()
            };

            // Add DRAFT account data based on selected gateway
            if (selectedGateway()) {
                switch (selectedGateway()) {
                    case "datax":
                        draftPaymentMethodData.datax_account = selectedAccount();
                        draftPaymentMethodData.account_number = accountNumber();
                        draftPaymentMethodData.routing_number = routingNumber();
                        break;
                    case "yodlee":
                        draftPaymentMethodData.selected_account = selectedAccount();
                        break;
                    default:
                        throw new Error("Invalid Draft gateway selected");
                }
            }

            return {
                method: this.getCode(),
                additional_data: {
                    payment_method_nonce: JSON.stringify(draftPaymentMethodData),
                },
            };
        },

        getPaymentMethodData: function () {
            const payment = window.checkoutConfig["payment"];
            const code = this.getCode();
            return payment[code];
        },

        getMerchantInfo: function () {
            const paymentData = this.getPaymentMethodData();
            return paymentData["clientInfo"];
        },

        isEnabled: function () {
            const paymentData = this.getPaymentMethodData();
            return paymentData["isEnabled"];
        },

        getNotes: function () {
            const paymentData = this.getPaymentMethodData();
            return paymentData["notes"];
        },

        getEnvironment: function () {
            const paymentData = this.getPaymentMethodData();
            return paymentData["baseUrl"];
        },

        draftImage: function () {
            const paymentData = this.getPaymentMethodData();
            return paymentData["imageUrl"];
        },

        getLoggedEmail: function () {
            return checkoutConfig.customerData.email
        }
    });
});
