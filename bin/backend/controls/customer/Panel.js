/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/ButtonSwitch',
    'qui/controls/windows/Confirm',
    'qui/utils/Form',
    'Users',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.Information.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/Panel.css'

], function (QUI, QUIPanel, QUIButtonSwitch, QUIConfirm, FormUtils, Users, QUILocale, QUIAjax, Mustache, templateInformation) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel',

        Binds: [
            '$onCreate',
            '$onDestroy',
            '$onShow',
            '$onSaveClick',
            '$onDeleteClick',
            '$onStatusChangeClick',
            '$clickEditAddress',
            '$openCategory',
            '$openAddressManagement',
            '$onUserDelete',
            '$onUserRefresh'
        ],

        options: {
            icon  : 'fa fa-user',
            userId: false
        },

        initialize: function (parent) {
            this.parent(parent);

            this.$User               = null;
            this.$userInitAttributes = null;

            this.addEvents({
                onCreate : this.$onCreate,
                onDestroy: this.$onDestroy,
                onShow   : this.$onShow
            });

            Users.addEvent('onDelete', this.$onUserDelete);
            Users.addEvent('onSwitchStatus', this.$onUserRefresh);
        },

        /**
         * the panel on destroy event
         * remove the binded events
         */
        $onDestroy: function () {
            Users.removeEvent('switchStatus', this.$onUserRefresh);
            Users.removeEvent('delete', this.$onUserDelete);
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
                self.Loader.hide();
            }).catch(function () {
                self.Loader.hide();
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

            this.addCategory({
                name  : 'addresses',
                text  : QUILocale.get(lg, 'quiqqer.customer.panel.addresses'),
                icon  : 'fa fa-address-card-o',
                events: {
                    onActive: function () {
                        self.$openCategory('addresses');
                    }
                }
            });

            this.getContent().setStyle('opacity', 0);

            // @todo load API

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

            var self = this,
                Form = this.getContent().getElement('form');

            // add address
            this.getContent()
                .getElement('[name="create-address"]')
                .addEvent('click', function (event) {
                    event.stop();

                    var Button = event.target;

                    if (Button.nodeName !== 'button') {
                        Button = Button.getParent('button');
                    }

                    Button.set('disabled', true);

                    var Icon = Button.getElement('.fa');

                    Icon.removeClass('fa-plus');
                    Icon.addClass('fa-spinner fa-spin');

                    self.createAddress().then(function (addressId) {
                        self.refreshAddressLists();
                        self.editAddress(addressId);

                        Button.set('disabled', false);
                        Icon.addClass('fa-plus');
                        Icon.removeClass('fa-spinner fa-spin');
                    });
                });

            // set data
            Form.elements.userId.value   = this.$User.getId();
            Form.elements.username.value = this.$User.getUsername();
            Form.elements.email.value    = this.$User.getAttribute('email');

            var DefaultAddress  = self.getContent().getElement('[name="address"]');
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

                            self.refreshAddressLists().then(function () {
                                self.Loader.hide();
                            });
                        }
                    }
                }).open();
            });
        },

        /**
         * Create a new address for this user
         *
         * @return {Promise}
         */
        createAddress: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.post('ajax_users_address_save', resolve, {
                    uid : self.$User.getId(),
                    aid : 0,
                    data: '[]'
                });
            });
        },

        /**
         * Refresh the address lists
         *
         * @return {Promise}
         */
        refreshAddressLists: function () {
            this.$User.$addresses = false; // workaround for refresh

            var self            = this;
            var DefaultAddress  = this.getContent().getElement('[name="address"]');
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

        //region address

        /**
         * open the address management
         *
         * @return {Promise}
         */
        $openAddressManagement: function () {
            var self = this;

            this.getContent().set('html', '');

            return new Promise(function (resolve) {
                require(['package/quiqqer/customer/bin/backend/controls/customer/AddressGrid'], function (AddressGrid) {
                    var Instance = new AddressGrid({
                        userId: self.getAttribute('userId')
                    }).inject(self.getContent());

                    Instance.resize();

                    resolve();
                });
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

                if (category === 'addresses') {
                    return self.$openAddressManagement();
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

            if (!Form) {
                Form = Content.getFirst();
            }

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

            if (!Form) {
                Form = Content.getFirst();
            }

            return new Promise(function (resolve) {
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
            var uid = this.$User.getId();

            new QUIConfirm({
                name       : 'DeleteUser',
                icon       : 'fa fa-trash-o',
                texticon   : 'fa fa-trash-o',
                title      : QUILocale.get('quiqqer/quiqqer', 'users.user.window.delete.title'),
                text       : QUILocale.get('quiqqer/quiqqer', 'users.user.window.delete.text', {
                    userid  : this.$User.getId(),
                    username: this.$User.getName()
                }),
                information: QUILocale.get('quiqqer/quiqqer', 'users.user.window.delete.information'),
                maxWidth   : 600,
                maxHeight  : 400,
                autoclose  : false,
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();
                        Users.deleteUsers([uid]).then(function () {
                            Win.close();
                        });
                    }
                }
            }).open();
        },

        /**
         * event: button status on / off change
         *
         * @param {Object} Button - qui/controls/buttons/ButtonSwitch
         */
        $onStatusChangeClick: function (Button) {
            var self         = this,
                buttonStatus = Button.getStatus(),
                userStatus   = this.$User.isActive();

            if (buttonStatus === userStatus || userStatus === -1) {
                return;
            }

            this.Loader.show();

            var Prom;

            if (buttonStatus) {
                Prom = this.$User.activate();
            } else {
                Prom = this.$User.deactivate();
            }

            Prom.then(function () {
                self.Loader.hide();
            });
        },

        //endregion

        //region user events

        /**
         * event on user delete
         *
         * @param {Object} Users - qui/classes/users/Manager
         * @param {Array} uids - user ids, which are deleted
         */
        $onUserDelete: function (Users, uids) {
            var uid = this.$User.getId();

            for (var i = 0, len = uids.length; i < len; i++) {
                if (parseInt(uid) === parseInt(uids[i])) {
                    this.destroy();
                    break;
                }
            }
        },

        /**
         * event: on user refresh
         * -> refresh the data in the panel
         */
        $onUserRefresh: function () {
            var Status = this.getButtons('status');

            if (this.$User.isActive()) {
                Status.setSilentOn();
                Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isActivate'));
            } else {
                Status.setSilentOff();
                Status.setAttribute('text', QUILocale.get('quiqqer/quiqqer', 'isDeactivate'));
            }
        }

        //endregion
    });
});