/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/SelectItem
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/SelectItem', [

    'qui/QUI',
    'qui/controls/elements/SelectItem',
    'Ajax'

], function(QUI, QUIElementSelectItem, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIElementSelectItem,
        Type: 'package/quiqqer/customer/bin/backend/controls/customer/SelectItem',

        Binds: [
            'refresh',
            '$onCustomerInject'
        ],

        initialize: function(options) {
            this.parent(options);
            this.setAttribute('icon', 'fa fa-user-o');

            this.addEvents({
                onInject: this.$onCustomerInject
            });
        },

        /**
         * Refresh the display
         *
         * @returns {Promise}
         */
        refresh: function() {
            const id = this.getAttribute('id');

            // user
            this.setAttribute('icon', 'fa fa-user-o');

            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_userDisplayName', (displayName) => {
                    this.$Text.set({
                        html: displayName,
                        title: displayName
                    });

                    resolve();
                }, {
                    'package': 'quiqqer/customer',
                    userId: id,
                    showAddressName: this.getAttribute('Parent').getAttribute('showAddressName') ? 1 : 0,
                    showError: false,
                    onError: () => {
                        this.destroy();
                        resolve();
                    }
                });
            });
        },

        $onCustomerInject: function() {
            this.getElm().setStyle('cursor', 'pointer');
            this.getElm().addEvent('dblclick', () => {
                if (!this.getAttribute('id')) {
                    return;
                }

                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel',
                    'utils/Panels'
                ], (Panel, PanelUtils) => {
                    PanelUtils.openPanelInTasks(
                        new Panel({
                            userId: this.getAttribute('id')
                        })
                    );
                });
            });
        }
    });
});
