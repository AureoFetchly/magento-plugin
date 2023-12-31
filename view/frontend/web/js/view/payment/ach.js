define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'ach',
                component: 'Edebit_Payment/js/view/payment/method-renderer/ach-method'
            }
        );
        return Component.extend({});
    }
);