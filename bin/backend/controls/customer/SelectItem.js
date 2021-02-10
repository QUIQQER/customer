/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/SelectItem
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/SelectItem', [

    'qui/QUI',
    'qui/controls/elements/SelectItem',
    'Ajax'

], function (QUI, QUIElementSelectItem, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIElementSelectItem,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/SelectItem',

        Binds: [
            'refresh'
        ],

        initialize: function (options) {
            this.parent(options);
            this.setAttribute('icon', 'fa fa-user-o');
        },

        /**
         * Refresh the display
         *
         * @returns {Promise}
         */
        refresh: function () {
            var id   = this.getAttribute('id'),
                Prom = Promise.resolve();

            // user
            this.setAttribute('icon', 'fa fa-user-o');

            var isnum = /^\d+$/.test(id);

            if (!isnum) {
                this.destroy();

                return Prom;
            }

            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_userDisplayName', function (displayName) {
                    self.$Text.set({
                        html : displayName,
                        title: displayName
                    });

                    resolve();
                }, {
                    'package'      : 'quiqqer/customer',
                    userId         : parseInt(id),
                    showAddressName: self.getAttribute('Parent').getAttribute('showAddressName') ? 1 : 0,
                    onError        : function (err) {
                        console.error(err);
                        self.destroy();
                        resolve();
                    }
                });
            });
        }
    });
});
