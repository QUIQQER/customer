/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/ButtonSwitch',
    'qui/utils/Form',
    'Users',
    'Locale',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.Information.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/Panel.css'

], function (QUI, QUIPanel, QUIButtonSwitch, FormUtils, Users, QUILocale, Mustache, templateInformation) {
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
            '$onStatusChangeClick',
            '$clickEditAddress',
            '$openCategory'
        ],

        options: {
            icon  : 'fa fa-user',
            userId: false
        },

        initialize: function (parent) {
            this.parent(parent);

            this.$User = null;

            this.$userInitAttributes = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onShow  : this.$onShow
            });
        },

        //region actions

        /**
         * alias for update
         *
         * @return {Promise}
         */
        save: function () {
            return this.update();
        },

        /**
         * Saves / Update the user
         */
        update: function () {
            var self = this;

            this.Loader.show();
            this.$categoryUnload();

            return this.$User.save().then(function () {
                self.Loader.show();
            });
        },

        //endregion

        /**
         * event: on create
         */
        $onCreate: function () {
            var self = this;

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
                name  : 'information',
                text  : QUILocale.get('quiqqer/quiqqer', 'information'),
                icon  : 'fa fa-file',
                events: {
                    onActive: function () {
                        self.$openCategory('information');
                    }
                }
            });

            // load API

            this.addCategory({
                name  : 'test',
                text  : 'test',
                icon  : 'fa fa-file',
                events: {
                    onActive: function () {
                        self.$openCategory('test');
                    }
                }
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

                self.$User               = User;
                self.$userInitAttributes = User.getAttributes();

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
                
                if (!self.$ActiveCat) {
                    self.getCategory('information').click();
                }

                self.Loader.hide();
            });
        },

        //region information

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
            var InvoiceAddress  = this.getContent().getElement('[name="quiqqer.erp.address"]');
            var DeliveryAddress = this.getContent().getElement('[name="quiqqer.delivery.address"]');

            var onChange = function (event) {
                var Target = event.target;
                var Button = Target.getNext('button');

                if (Target.value === '') {
                    Button.set('disabled', true);
                } else {
                    Button.set('disabled', false);
                }
            };

            DefaultAddress.addEvent('change', onChange);
            InvoiceAddress.addEvent('change', onChange);
            DeliveryAddress.addEvent('change', onChange);

            DefaultAddress.getNext('button').addEvent('click', this.$clickEditAddress);
            InvoiceAddress.getNext('button').addEvent('click', this.$clickEditAddress);
            DeliveryAddress.getNext('button').addEvent('click', this.$clickEditAddress);

            return self.refreshAddressLists();
        },

        /**
         * Edit an address
         * - opens the edit address window
         *
         * @param addressId
         * @return {Promise}
         */
        editAddress: function (addressId) {
            var self = this;

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow'
            ], function (AddressEditWindow) {
                new AddressEditWindow({
                    addressId: addressId,
                    events   : {
                        onSubmit: function () {
                            self.Loader.show();

                            self.$User.$addresses = false; // workaround for refresh
                            self.refreshAddressLists().then(function () {
                                self.Loader.hide();
                            });
                        }
                    }
                }).open();
            });
        },

        /**
         * Refresh the address lists
         *
         * @return {Promise}
         */
        refreshAddressLists: function () {
            var self            = this;
            var DefaultAddress  = this.getContent().getElement('[name="default-address"]');
            var InvoiceAddress  = this.getContent().getElement('[name="quiqqer.erp.address"]');
            var DeliveryAddress = this.getContent().getElement('[name="quiqqer.delivery.address"]');

            // set addresses
            return this.$User.getAddressList().then(function (addressList) {
                DefaultAddress.innerHTML  = '';
                InvoiceAddress.innerHTML  = '';
                DeliveryAddress.innerHTML = '';

                new Element('option', {
                    html : '',
                    value: ''
                }).inject(DefaultAddress);

                new Element('option', {
                    html : '',
                    value: ''
                }).inject(InvoiceAddress);

                new Element('option', {
                    html : '',
                    value: ''
                }).inject(DeliveryAddress);

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
                    DefaultAddress.getNext('button').set('disabled', false);
                }

                if (invoiceAddress) {
                    InvoiceAddress.value = invoiceAddress;
                    InvoiceAddress.getNext('button').set('disabled', false);
                }

                if (deliveryAddress) {
                    DeliveryAddress.value = deliveryAddress;
                    DeliveryAddress.getNext('button').set('disabled', false);
                }
            });
        },

        //endregion

        //region category stuff

        /**
         * Opens a category panel
         *
         * @param {String} category - name of the category
         */
        $openCategory: function (category) {
            var self = this;

            this.$categoryUnload();

            this.$hideCategory().then(function () {
                if (category === 'information') {
                    return self.$openInformation();
                }
            }).then(function () {
                self.$showCategory();
            });
        },

        /**
         * set the form data to the user
         */
        $categoryUnload: function () {
            var Content = this.getContent(),
                Form    = Content.getElement('form');

            var data = FormUtils.getFormData(Form);

            if (typeof data.id !== 'undefined') {
                delete data.id;
            }

            this.$User.setAttributes(data);
        },

        /**
         * hide category
         *
         * @return {Promise}
         */
        $hideCategory: function () {
            var Content = this.getContent(),
                Form    = Content.getElement('form');

            return new Promise(function (resolve) {
                if (!Form) {
                    resolve();
                    return;
                }

                Form.setStyle('position', 'relative');

                moofx(Form).animate({
                    opacity: 0,
                    top    : -50
                }, {
                    duration: 250,
                    callback: function () {
                        Content.setStyle('opacity', 0);
                        Form.destroy();
                        resolve();
                    }
                });
            });
        },

        /**
         * show category
         *
         * @return {Promise}
         */
        $showCategory: function () {
            var Content = this.getContent(),
                Form    = Content.getElement('form');

            return new Promise(function (resolve) {
                if (!Form) {
                    resolve();
                    return;
                }

                Form.setStyle('position', 'relative');
                Form.setStyle('top', -50);
                Form.setStyle('opacity', 0);

                Content.setStyle('opacity', 1);

                moofx(Form).animate({
                    opacity: 1,
                    top    : 0
                }, {
                    duration: 250,
                    callback: resolve
                });
            });
        },

        //endregion

        //region button actions

        /**
         * event: on address edit click
         *
         * @param event
         */
        $clickEditAddress: function (event) {
            var Target = event.target;

            if (Target.nodeName !== 'BUTTON') {
                Target = Target.getParent('button');
            }

            var Select = Target.getPrevious();

            if (Select.value === '') {
                return;
            }

            this.editAddress(parseInt(Select.value));
        },

        /**
         * event: on save click
         */
        $onSaveClick: function () {
            this.Loader.show();
            this.update().then(function () {
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * event: on delete click
         */
        $onDeleteClick: function () {

        },

        /**
         * event: status change click
         */
        $onStatusChangeClick: function () {

        }

        //endregion
    });
});