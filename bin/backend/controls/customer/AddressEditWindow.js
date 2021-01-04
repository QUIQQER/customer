/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow
 * @author www.csg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/customer/bin/backend/controls/customer/AddressEdit',
    'Locale',

    'css!package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow.css'

], function (QUI, QUIConfirm, AddressEdit, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            addressId: false,
            maxHeight: 700,
            maxWidth : 600,
            autoclose: false,
            icon     : 'fa fa-share'
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this;

            this.Loader.show();
            this.getContent().set('html', '');
            this.getContent().addClass('quiqqer-customer-window-edit-address');

            this.$Address = new AddressEdit({
                addressId: this.getAttribute('addressId'),
                events   : {
                    onLoad: function () {
                        self.setAttribute('title', QUILocale.get('quiqqer/customer', 'address.edit.window.title', {
                            id       : self.$Address.getAddressId(),
                            firstname: self.$Address.getFirstname(),
                            lastname : self.$Address.getLastname()
                        }));

                        self.refresh();

                        self.fireEvent('load', [self]);
                        self.Loader.hide();
                    }
                }
            }).inject(this.getContent());
        },

        /**
         * submit
         */
        submit: function () {
            var self = this;

            this.Loader.show();

            this.$Address.update().then(function () {
                self.fireEvent('submit', [self]);
                self.close();
            });
        }
    });
});
