define([], function () {
    function PaymentUtils(baseUrl, apiKey, merchantKey, paymentType)
    {
        async function authorize(user, gateway)
        {
            const queryParams = new URLSearchParams({
                consumer_name: user.name,
                consumer_email: user.consumer_email,
                consumer_phone: user.consumer_phone,
                is_email: !user.is_phone_number,
                payment_method: paymentType,
                channel: 'magento',
                validation_service: gateway
            });
            const url = baseUrl + 'transactions/authorize_user?' + queryParams;
            const response = await fetch(url, {
                headers: {
                    Authorization: `apikey ${apiKey}:${merchantKey}`,
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            return await response.json()
        }

        async function getToken(user, otp, gateway)
        {
            const endpoint = `${baseUrl}${gateway}/request_token`;
            const queryParams = new URLSearchParams({
                consumer_email: user.consumer_email,
                consumer_phone: user.consumer_phone,
                is_email: !user.is_phone_number,
                code: otp,
                payment_method: paymentType,
                channel: 'magento'
            });
            const response = await fetch(`${endpoint}?${queryParams}`, {
                headers: {
                    Authorization: `apikey ${apiKey}:${merchantKey}`,
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            return response.json();
        }

        async function updateVerifiedAccount(data)
        {
            const endpoint = `${baseUrl}yodlee/update_verified_account`;
            const response = await fetch(endpoint, {
                method: "PUT",
                body: JSON.stringify(data),
                headers: {
                    "Content-type": "application/json; charset=UTF-8",
                },
            });
            return await response.json();
        }

        return {
            authorize,
            getToken,
            updateVerifiedAccount,
        };
    }

    return PaymentUtils;
});
