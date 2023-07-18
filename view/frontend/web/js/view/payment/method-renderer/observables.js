define([
  "jquery",
  "ko",
  "Magento_Ui/js/model/messageList",
  "Magento_Checkout/js/model/quote",
  "Magento_Checkout/js/model/full-screen-loader",
  "mage/translate",
], function ($, ko, messageList, quote, fullScreenLoader, $t) {
  const PHONE_REGEX = /^(1\s?)?(\d{3}|\(\d{3}\))[\s-]?\d{3}[\s-]?\d{4}$/;
  const EMAIL_REGEX = /^\w+([.-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,3})+$/;

  const getBillingInfo = function (self) {
    const { billingAddress, guestEmail } = quote;
    const email = guestEmail ? guestEmail : self.getLoggedEmail();
    return { billingAddress: billingAddress(), guestEmail: email };
  };

  const extractUser = function () {
    const self = this;
    const { emailOrPhone } = self;
    const { billingAddress, guestEmail } = getBillingInfo(self);
    const name = `${billingAddress.firstname} ${billingAddress.lastname}`;
    let consumer_email = guestEmail;
    let consumer_phone = billingAddress.telephone;
    let is_phone_number = false;

    if (emailOrPhone().match(PHONE_REGEX)) {
      consumer_phone = formatPhoneNumber(emailOrPhone(), false);
      is_phone_number = true;
    } else if (emailOrPhone().match(EMAIL_REGEX)) {
      consumer_email = emailOrPhone();
    }
    return { name, consumer_phone, consumer_email, is_phone_number };
  };

  const formatPhoneNumber = function (phoneNumberString, format = true) {
    const cleaned = phoneNumberString.replace(/\D/g, "");
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    const formatted = `${match[1]}-${match[2]}-${match[3]}`;
    const unformatted = `${match[1]}${match[2]}${match[3]}`;
    return match ? (format ? formatted : unformatted) : null;
  };

  const getEmailOrPhone = (emailOrPhone) => {
    const isPhone = PHONE_REGEX.test(emailOrPhone);
    return isPhone ? formatPhoneNumber(emailOrPhone, false) : emailOrPhone;
  };

  const emailOrPhoneValidator = (value) => {
    return EMAIL_REGEX.test(value) || PHONE_REGEX.test(value);
  };

  const setErrorMsg = (message) => {
    if (!messageList.hasMessages()) {
      messageList.addErrorMessage({ message });
      $("html, body").animate(
        {
          scrollTop: 0,
        },
        {
          duration: 1000,
          easing: "swing",
          complete: function () {
            setTimeout(() => {
              messageList.clear();
            }, 4000);
          },
        }
      );
    }
  };

  const hideModal = function () {
    const self = this;
    if ($(`#${self.container}, .chase-modal`).is(":visible")) {
      $(".multi-step-form__content").hide();
    }
    if ($(".chase-modal").is(":visible")) {
      $(".payment-option-header__logo").hide();
    }
    if (self.currentStep() === 3) {
      $(".payment-option-header__logo").show();
    }
  };

  const showPaymentModal = function () {
    const self = this;
    $(document).ready(function () {
      hideModal.call(self);
      $(".multi-step-form__content").show();
    });
  };

  const tick = function () {
    const self = this;
    const newTime = self.timer() - 1;
    self.timer(newTime);
    if (newTime === 0) {
      clearInterval(self.intervalID);
    }
  };

  const isOtpOptional = (self) => {
    return (
      self.getCode() === "draft" &&
      self.isOtpOptional() &&
      self.selectedGateway() === "datax"
    );
  };

  const handleGatewaySelection = async function () {
    const self = this;
    if (isOtpOptional(self)) {
      const { guestEmail } = getBillingInfo(self);
      self.emailOrPhone(guestEmail);
      await authorizeUser.call(self);
    } else {
      self.currentStep(1);
    }
  };

  const handleAuthorizeUser = async function () {
    const self = this;
    if (self.hasAuthorization()) {
      if (emailOrPhoneValidator(self.emailOrPhone())) {
        await authorizeUser.call(self);
      } else {
        setErrorMsg($t("Please enter a valid phone number or an e-mail"));
      }
    } else {
      setErrorMsg($t("You need to accept the terms to continue"));
    }
  };

  const handleTokenRequest = async function () {
    const self = this;
    if (self.otpCode()) {
      if (self.getCode() === "draft" && self.selectedGateway() === "yodlee") {
        const user = extractUser.call(self);
        const { selectedGateway, otpCode } = self;
        const gateway = selectedGateway();
        const otp = otpCode();
        const { success, error } = await self.paymentUtils.getToken(
          user,
          otp,
          gateway
        );
        if (success) {
          self.isChase(true);
          hideModal.call(self);
          return;
        } else {
          setErrorMsg($t(`Failed to get access token: ${error}`));
        }
      }
      await requestAccountsToken.call(self);
    } else {
      setErrorMsg($t("OTP code can't be empty"));
    }
  };

  const handlePlaceOrder = function () {
    const self = this;
    const selectedGateway = self.selectedGateway();
    const existingAccounts = self.existingAccounts();

    if (self.getCode() === "ach" && !self.achTerms()) {
      return setErrorMsg($t("You need to accept the terms to continue"));
    }

    if (!self.termsCondition()) {
      return setErrorMsg($t("You need to agree with the terms and services"));
    }

    if (selectedGateway === "datax" && !existingAccounts.length) {
      const routingNumber = self.routingNumber()?.trim();
      const accountNumber = self.accountNumber()?.trim();
      const confirmAccountNumber = self.confirmAccountNumber()?.trim();

      if (!routingNumber) {
        return setErrorMsg($t("Please enter a routing number."));
      }
      if (!accountNumber) {
        return setErrorMsg($t("Please enter an account number."));
      }
      if (!confirmAccountNumber) {
        return setErrorMsg($t("Please confirm your account number."));
      }

      if (routingNumber.length < 9) {
        return setErrorMsg($t("Routing number should be at least 9 digits."));
      }
      if (accountNumber.length < 3) {
        return setErrorMsg($t("Account number should be at least 3 digits."));
      }
      if (accountNumber.length > 17) {
        return setErrorMsg(
          $t("Account number should be a maximum of 17 digits.")
        );
      }
      if (accountNumber !== confirmAccountNumber) {
        return setErrorMsg($t("Please make sure your account number match."));
      }
    }

    self.placeOrder();
  };

  const authorizeUser = async function () {
    const self = this;
    const user = extractUser.call(self);
    const selectedGateway = self.selectedGateway();
    try {
      fullScreenLoader.startLoader();
      const { new_customer, success, description } =
        await self.paymentUtils.authorize(user, selectedGateway);
      if (success) {
        console.debug(
          `${
            new_customer ? "New" : "Existing"
          } customer authorized successfully.`
        );
        if (isOtpOptional(self)) {
          await requestAccountsToken.call(self);
        } else {
          self.currentStep(2);
          self.timer(300);
        }
      } else {
        setErrorMsg($t(`Error authorizing user: ${description}`));
      }
    } catch (error) {
      setErrorMsg($t(`Network error: ${error.message}`));
    } finally {
      fullScreenLoader.stopLoader();
    }
  };

  const requestAccountsToken = async function () {
    const self = this;
    const { selectedGateway, otpCode } = self;
    const gateway = selectedGateway();
    const otp = otpCode();
    const user = extractUser.call(self);
    try {
      fullScreenLoader.startLoader();
      const { accounts, success, token, uuid, error } =
        await self.paymentUtils.getToken(user, otp, gateway);
      if (success) {
        self.token = token;
        self.uuid = uuid;
        self.currentStep(3);
        if (accounts.length) {
          console.debug(
            `${accounts.length} accounts were successfully retrieved.`
          );
          self.existingAccounts(accounts);
          return;
        }
        if (gateway === "yodlee" && !accounts.length) {
          console.debug("No accounts found for Yodlee");
          fastLink.call(self);
        }
      } else {
        setErrorMsg($t(`Failed to get access token: ${error}`));
      }
    } catch (error) {
      setErrorMsg($t(`Network error: ${error.message}`));
    } finally {
      fullScreenLoader.stopLoader();
    }
  };

  const fastLink = function () {
    const self = this;
    const { token, uuid } = self;
    const { billingAddress, guestEmail } = getBillingInfo(self);
    const customer_email = guestEmail;
    const customer_name = `${billingAddress.firstname} ${billingAddress.lastname}`;
    const customer_phone = billingAddress.telephone;
    const customer_street = billingAddress.street[0];
    const customer_city = billingAddress.city;
    const customer_state = billingAddress.regionCode;
    const customer_zip = billingAddress.postcode;
    const customer_email_or_phone = getEmailOrPhone(self.emailOrPhone());

    $(`#${self.container}`).show();

    ((window) => {
      window["fastlink"].open(
        {
          forceRedirect: true,
          fastLinkURL:
            "https://fl4.preprod.yodlee.com/authenticate/USDevexPreProd4-104/fastlink?channelAppName=usdevexpreprod4",
          accessToken: "Bearer " + token,
          params: {
            configName: "edebit_verification",
          },
          onClose: async function (e) {
            if (e["sites"][0].status === "SUCCESS") {
              const response = await self.paymentUtils.updateVerifiedAccount({
                customer_email_or_phone,
                customer_phone,
                customer_email,
                customer_name,
                customer_street,
                customer_city,
                customer_state,
                customer_zip,
                uuid,
                channel: "magento",
              });
              if (response.success) {
                self.existingAccounts(response["accounts"]);
                showPaymentModal.call(self);
              } else {
                self.emailOrPhone(null);
                self.otpCode(null);
                self.hasAuthorization(false);
                self.termsCondition(false);
                self.currentStep(1);
                setErrorMsg($t(response.errors[0]));
                showPaymentModal.call(self);
              }
            }
          },
        },
        self.container
      );
    })(window);

    $(document).ready(function () {
      hideModal.call(self);
    });
  };

  return function (self) {
    self.emailOrPhone = ko.observable(null);
    self.otpCode = ko.observable(null);
    self.selectedGateway = ko.observable(null);
    self.routingNumber = ko.observable(null);
    self.accountNumber = ko.observable(null);
    self.confirmAccountNumber = ko.observable(null);
    self.selectedAccount = ko.observable(null);
    self.maxLength = ko.observable(null);
    self.hasAuthorization = ko.observable(false);
    self.termsCondition = ko.observable(false);
    self.saveBankAccount = ko.observable(true);
    self.isNewAccount = ko.observable(false);
    self.currentStep = ko.observable(0);
    self.existingAccounts = ko.observableArray([]);
    self.sentTo = ko.pureComputed(() => {
      const emailOrPhone = self.emailOrPhone();
      if (!PHONE_REGEX.test(emailOrPhone)) {
        return emailOrPhone;
      }
      return formatPhoneNumber(emailOrPhone);
    });

    self.emailOrPhone.subscribe((eventValue) => {
      if (self.currentStep() === 1) {
        const isPhone = PHONE_REGEX.test(eventValue?.toString());
        self.maxLength(isPhone ? 14 : null);
        self.emailOrPhone(isPhone ? formatPhoneNumber(eventValue) : eventValue);
      }
    });

    ko.computed(() => {
      if (self.currentStep() === 2) {
        self.timer = ko.observable(300); // 5 minutes in seconds
        self.intervalID = setInterval(tick.bind(self), 1000);
      }
    });

    self.nextStep = async function (data) {
      try {
        fullScreenLoader.startLoader();
        switch (self.currentStep()) {
          case 0:
            const gateway = (data || self.gateways()[0]).toLowerCase();
            self.selectedGateway(gateway);
            await handleGatewaySelection.call(self);
            break;

          case 1:
            await handleAuthorizeUser.call(self);
            break;

          case 2:
            await handleTokenRequest.call(self);
            break;

          case 3:
            await handlePlaceOrder.call(self);
            break;
          default:
            console.debug("Invalid step:", self.currentStep());
        }
      } catch (error) {
        console.error("Next step error:", error);
      } finally {
        // Stop loader
        fullScreenLoader.stopLoader();
      }
    };

    self.getBankAccounts = function () {
      const selectedGateway = self.selectedGateway();
      const existingAccounts = self.existingAccounts();
      return existingAccounts.map((account) => {
        const value = account.id;
        let name = account.name;
        if (selectedGateway === "yodlee") {
          name = `${account.info?.["provider_name"]} - ${account.info.account_number}`;
        }
        return { value, name };
      });
    };

    self.prevStep = function () {
      const currentStep = self.currentStep();

      if (self.getCode() === "ach") {
        self.achTerms(false);
      }

      if (currentStep === 2) {
        const selector = ".chase-modal";
        if ($(selector).is(":visible")) {
          self.isChase(false);
          $(selector).hide();
          $(".payment-option-header__logo").show();
          $(".multi-step-form__content").show();
        }
      }

      if (currentStep === 3) {
        if (self.selectedGateway() === "yodlee") {
          $(`#${self.container}`).hide();
          showPaymentModal.call(self);
        }
        if (!isOtpOptional(self)) {
          self.timer(0);
          self.otpCode(null);
        }
        self.emailOrPhone(null);
        self.termsCondition(false);
        self.hasAuthorization(false);
        self.currentStep(0);
        self.existingAccounts([]);
      } else if (currentStep !== 0) {
        self.currentStep(currentStep - 1);
      }
    };

    self.resendCode = async function () {
      await authorizeUser.call(self);
      self.timer(300);
      self.intervalID = setInterval(tick.bind(self), 1000);
    };

    self.getExpiryTime = function () {
      const minutes = Math.floor(self.timer() / 60);
      const seconds = self.timer() % 60;
      const formattedMinutes = ("0" + minutes).slice(-2); // Add leading zero if necessary
      const formattedSeconds = ("0" + seconds).slice(-2); // Add leading zero if necessary
      return (
        "Code expires in " +
        formattedMinutes +
        " min and " +
        formattedSeconds +
        " sec"
      );
    };

    self.addNewAccount = function () {
      self.existingAccounts([]);
      self.selectedAccount(null);
      if (self.selectedGateway() === "yodlee") {
        fastLink.call(self);
      }
    };

    self.authorizeUser = authorizeUser;
    self.showPaymentModal = showPaymentModal;
    self.getEmailOrPhone = getEmailOrPhone;
    self.requestAccountsToken = requestAccountsToken;
  };
});
