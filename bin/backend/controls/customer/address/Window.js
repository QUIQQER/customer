/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/address/Window
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/address/Window', [

    'qui/controls/windows/Confirm',
    'Locale',
    'Users'

], function (QUIConfirm, QUILocale, Users) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/address/Window',

        Binds: [
            '$onOpen'
        ],

        options: {
            userId   : false,
            icon     : 'fa fa-address-book-o',
            title    : QUILocale.get(lg, 'window.customer.address.select.title'),
            maxHeight: 300,
            maxWidth : 550,
            autoclose: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$addressList = [];

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        $onOpen: function (Win) {
            var self = this;

            Win.Loader.show();

            Win.getContent()
                .set('html', QUILocale.get(lg, 'window.customer.address.select.information'));

            var Select = new Element('select', {
                styles: {
                    display: 'block',
                    clear  : 'both',
                    margin : '1rem auto 0',
                    width  : 500
                }
            }).inject(Win.getContent());

            this.$getUser().then(function (User) {
                return self.getAddressList(User);
            }).then(function (addresses) {
                self.$addressList = addresses;

                for (var i = 0, len = addresses.length; i < len; i++) {
                    let text = addresses[i].text;

                    if (addresses[i].default) {
                        text = QUILocale.get(lg, 'window.customer.address.select.default_prefix') + ' ' + text;
                    }

                    new Element('option', {
                        value: addresses[i].uuid,
                        html : text
                    }).inject(Select);
                }

                Win.Loader.hide();
            });
        },

        /**
         * Return the loaded user object
         *
         * @returns {Promise}
         */
        $getUser: function () {
            var userId = this.getAttribute('userId');

            if (!userId || userId === '') {
                return Promise.reject();
            }

            var User = Users.get(userId);

            if (!User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         *
         * @param User
         * @return {Promise}
         */
        getAddressList: function (User) {
            var self = this;

            return new Promise(function (resolve, reject) {
                return User.getAddressList().then(function (result) {
                    if (result.length) {
                        return resolve(result);
                    }

                    // create new address
                    return self.openCreateAddressDialog(User).then(function () {
                        return User.getAddressList().then(resolve);
                    }).catch(reject);
                }).catch(function () {
                    resolve([]);
                });
            });
        },

        /**
         * Submit the window
         *
         * @method qui/controls/windows/Confirm#submit
         */
        submit: function () {
            var addressId = this.getElm().getElement('select').value;
            var address   = this.$addressList.filter(function (address) {
                return address.id === addressId;
            });

            if (address.length) {
                address = address[0];
            }

            this.fireEvent('submit', [this, addressId, address]);

            if (this.getAttribute('autoclose')) {
                this.close();
            }
        }
    });
});
