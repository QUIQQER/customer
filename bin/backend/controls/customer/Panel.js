/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/ButtonSwitch',
    'Users',
    'Locale',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.Information.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/Panel.css'

], function (QUI, QUIPanel, QUIButtonSwitch, Users, QUILocale, Mustache, templateInformation) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel',

        Binds: [
            '$onCreate',
            '$onShow',
            '$onSaveClick',
            '$onDeleteClick',
            '$onStatusChangeClick'
        ],

        options: {
            icon  : 'fa fa-user',
            userId: false
        },

        initialize: function (parent) {
            this.parent(parent);

            this.$User = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onShow  : this.$onShow
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            this.getElm().addClass('quiqqer-customer-panel');

            this.addButton({
                name     : 'userSave',
                text     : QUILocale.get('quiqqer/quiqqer', 'users.user.btn.save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: this.$onSaveClick
                }
            });

            this.addButton({
                type: 'separator'
            });

            this.addButton(
                new QUIButtonSwitch({
                    name    : 'status',
                    text    : QUILocale.get('quiqqer/quiqqer', 'isActivate'),
                    status  : true,
                    disabled: true,
                    events  : {
                        onChange: this.$onStatusChangeClick
                    }
                })
            );

            this.addButton({
                name  : 'userDelete',
                title : QUILocale.get('quiqqer/quiqqer', 'users.user.btn.delete'),
                icon  : 'fa fa-trash-o',
                events: {
                    onClick: this.$onDeleteClick
                },
                styles: {
                    'float': 'right'
                }
            });

            this.addCategory({
                name: 'information',
                text: QUILocale.get('quiqqer/quiqqer', 'information'),
                icon: 'fa fa-file'
            });
        },

        /**
         * event: on show
         */
        $onShow: function () {
            this.Loader.show();

            var self   = this;
            var User   = Users.get(this.getAttribute('userId'));
            var Loaded = Promise.resolve(User);

            if (!User.isLoaded()) {
                Loaded = User.load();
            }

            Loaded.then(function (User) {
                var Status = self.getButtons('status');

                self.$User = User;
                self.setAttribute('title', QUILocale.get(lg, 'customer.panel.title', {
                    username: User.getUsername(),
                    user    : User.getName()
                }));

                self.refresh();

                // active status
                if (User.isActive() === -1) {
                    Status.setSilentOff();
                    Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isDeactivate'));
                    Status.disable();
                    return;
                }

                Status.enable();

                if (!User.isActive()) {
                    Status.off();
                    Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isDeactivate'));
                } else {
                    Status.on();
                    Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isActivate'));
                }


                self.$openInformation();
                self.Loader.hide();
            });
        },

        //region categories

        /**
         * opens the user information
         */
        $openInformation: function () {
            this.getContent().set('html', Mustache.render(templateInformation, {
                detailsTitle: QUILocale.get(lg, 'customer.panel,information.details'),
                textUserId  : QUILocale.get('quiqqer/quiqqer', 'user_id'),
                textUsername: QUILocale.get('quiqqer/quiqqer', 'username'),
                textEmail   : QUILocale.get('quiqqer/quiqqer', 'email'),

                titleAddress        : QUILocale.get('quiqqer/quiqqer', 'address'),
                titleDeliveryAddress: QUILocale.get(lg, 'customer.panel,information.erp.addresses'),
                textInvoiceAddress  : QUILocale.get(lg, 'customer.panel,information.invoice.address'),
                textDeliveryAddress : QUILocale.get(lg, 'customer.panel,information.delivery.address')
            }));

            // set data
            var self = this,
                Form = this.getContent().getElement('form');

            Form.elements.userId.value   = this.$User.getId();
            Form.elements.username.value = this.$User.getUsername();
            Form.elements.email.value    = this.$User.getAttribute('email');

            var DefaultAddress  = self.getContent().getElement('[name="default-address"]');
            var InvoiceAddress  = self.getContent().getElement('[name="invoice-address"]');
            var DeliveryAddress = self.getContent().getElement('[name="delivery-address"]');

            // set addresses
            return self.$User.getAddressList().then(function (addressList) {
                for (var i = 0, len = addressList.length; i < len; i++) {
                    new Element('option', {
                        html : addressList[i].text,
                        value: parseInt(addressList[i].id)
                    }).inject(DefaultAddress);

                    new Element('option', {
                        html : addressList[i].text,
                        value: parseInt(addressList[i].id)
                    }).inject(InvoiceAddress);

                    new Element('option', {
                        html : addressList[i].text,
                        value: parseInt(addressList[i].id)
                    }).inject(DeliveryAddress);
                }


                var attributes      = self.$User.getAttributes(),
                    address         = parseInt(attributes.address),
                    invoiceAddress  = parseInt(attributes['quiqqer.erp.address']),
                    deliveryAddress = parseInt(attributes['quiqqer.delivery.address']);

                if (address) {
                    DefaultAddress.value = address;
                }

                if (invoiceAddress) {
                    InvoiceAddress.value = invoiceAddress;
                }

                if (deliveryAddress) {
                    DeliveryAddress.value = deliveryAddress;
                }

                console.log(attributes);
            });
        },

        //endregion

        //region button actions

        $onSaveClick: function () {

        },

        $onDeleteClick: function () {

        },

        $onStatusChangeClick: function () {

        }

        //endregion
    });
});