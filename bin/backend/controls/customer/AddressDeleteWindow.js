/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow
 * @author www.csg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/AddressDeleteWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'Locale',
    'Ajax'

], function(QUI, QUIConfirm, QUILocale, QUIAjax) {
    'use strict';

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIConfirm,
        Type: 'package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            addressId: false,
            maxHeight: 300,
            maxWidth: 500,
            autoclose: false,
            icon: 'fa fa-trash'
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get(lg, 'customer.address.delete.title'),
                text: QUILocale.get(lg, 'customer.address.delete.text'),
                information: '',
                texticon: 'fa fa-trash',
                icon: 'fa fa-trash'
            });

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function() {
            var self = this,
                addressIds = this.getAttribute('addressId');

            if (typeOf(addressIds) !== 'array') {
                this.close();
                return;
            }

            this.Loader.show();

            QUIAjax.post('ajax_users_address_getAddressList', function(addresses) {
                var list = '<ul>';

                for (var i = 0, len = addresses.length; i < len; i++) {
                    list = list + '<li>#' + addresses[i].id + ' - ' + addresses[i].text + '</li>';
                }

                list = list + '</ul>';

                self.setAttribute('information', QUILocale.get(lg, 'customer.address.delete.information', {
                    addressIds: list
                }));

                self.Loader.hide();
            }, {
                ids: JSON.encode(addressIds)
            });
        },

        /**
         * submit
         */
        submit: function() {
            var self = this;

            this.Loader.show();

            QUIAjax.post('ajax_users_address_deleteAddressList', function() {
                self.fireEvent('submit', [self]);
                self.close();
            }, {
                ids: JSON.encode(this.getAttribute('addressId'))
            });
        }
    });
});
