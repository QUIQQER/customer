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
        },

        /**
         * Opens the customer panel
         *
         * @param customerId
         */
        openCustomer: function (customerId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel',
                    'utils/Panels'
                ], function (Panel, PanelUtils) {
                    var CustomerPanel = new Panel({
                        userId: customerId
                    });

                    PanelUtils.openPanelInTasks(CustomerPanel);
                    resolve(CustomerPanel);
                });
            });
        },

        /**
         * add a address to customer
         *
         * @param userId
         * @return {Promise}
         */
        addAddressToCustomer: function (userId) {
            return new Promise(function (resolve) {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_customer_addAddress', resolve, {
                    'package': 'quiqqer/customer',
                    userId   : userId
                });
            });
        }
    });
});
