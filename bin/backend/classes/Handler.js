/**
 * @module package/quiqqer/customer/bin/backend/classes/Handler
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/classes/Handler', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QDOM,
        Type   : 'package/quiqqer/customer/bin/backend/classes/Handler',

        initialize: function (parent) {
            this.parent(parent);
        },

        /**
         *
         * @return {Promise}
         */
        getCustomerGroupId: function () {
            return new Promise(function (resolve) {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_getCustomerGroupId', resolve, {
                    'package': 'quiqqer/customer'
                });
            });
        },

        /**
         * add user to customer group
         *
         * @param userId
         * @return {Promise}
         */
        addToCustomer: function (userId) {
            return new Promise(function (resolve) {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_addToCustomer', resolve, {
                    'package': 'quiqqer/customer',
                    userId   : userId
                });
            });
        },

        /**
         * remove user from customer group
         *
         * @param userId
         * @return {Promise}
         */
        removeFromCustomer: function (userId) {
            return new Promise(function (resolve) {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_removeFromCustomer', resolve, {
                    'package': 'quiqqer/customer',
                    userId   : userId
                });
            });
        }
    });
});