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
                type: 'draft',
                component: 'Edebit_Payment/js/view/payment/method-renderer/draft-method'
            }
        );
        return Component.extend({});
    }
);