/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/ButtonSwitch',
    'qui/controls/windows/Confirm',
    'package/quiqqer/countries/bin/Countries',
    'qui/utils/Form',
    'Users',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.Information.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/Panel.css'

], function (QUI, QUIPanel, QUIButtonSwitch, QUIConfirm, Countries,
             FormUtils, Users, QUILocale, QUIAjax, Mustache, templateInformation) {
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
            '$clickEditAddress',
            '$openCategory',
            '$openAddressManagement',
            '$onUserDelete',
            '$onCustomerCategoryActive'
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
        },

        /**
         * the panel on destroy event
         * remove the binded events
         */
        $onDestroy: function () {
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

            return new Promise(function (resolve) {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_customer_save', resolve, {
                    'package': 'quiqqer/customer',
                    userId   : self.$User.getId(),
                    data     : JSON.encode(self.$User.getAttributes()),
                    onError  : resolve
                });
            }).then(function () {
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
                text  : QUILocale.get(lg, 'quiqqer.customer.panel.general'),
                icon  : 'fa fa-file',
                events: {
                    onActive: function () {
                        self.$openCategory('information');
                    }
                }
            });

            this.addCategory({
                name  : 'userInformation',
                text  : QUILocale.get(lg, 'quiqqer.customer.panel.userInformation'),
                icon  : 'fa fa-user',
                events: {
                    onActive: function () {
                        self.$openCategory('userInformation');
                    }
                }
            });

            this.addCategory({
                name  : 'userProperty',
                text  : QUILocale.get(lg, 'quiqqer.customer.panel.userProperty'),
                icon  : 'fa fa-user',
                events: {
                    onActive: function () {
                        self.$openCategory('userProperty');
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

            this.addCategory({
                name  : 'comments',
                text  : QUILocale.get(lg, 'quiqqer.customer.panel.comments'),
                icon  : 'fa fa-comments',
                events: {
                    onActive: function () {
                        self.$openCategory('comments');
                    }
                }
            });

            this.getContent().setStyle('opacity', 0);

            QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getCategories', function (result) {
                var categories = result.categories;

                for (var i = 0, len = categories.length; i < len; i++) {
                    categories[i].events = {
                        onActive: self.$onCustomerCategoryActive
                    };

                    self.addCategory(categories[i]);
                }
            }, {
                package: 'quiqqer/customer'
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
                self.$User               = User;
                self.$userInitAttributes = User.getAttributes();

                self.setAttribute('title', QUILocale.get(lg, 'customer.panel.title', {
                    username: User.getUsername(),
                    user    : User.getName()
                }));

                self.refresh();

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
                detailsTitle      : QUILocale.get(lg, 'customer.panel,information.details'),
                textUserId        : QUILocale.get('quiqqer/quiqqer', 'user_id'),
                textCustomerId    : QUILocale.get(lg, 'customerId'),
                textSalutation    : QUILocale.get('quiqqer/quiqqer', 'salutation'),
                textFirstname     : QUILocale.get('quiqqer/quiqqer', 'firstname'),
                textLastname      : QUILocale.get('quiqqer/quiqqer', 'lastname'),
                titleAddress      : QUILocale.get('quiqqer/quiqqer', 'address'),
                textStreet        : QUILocale.get('quiqqer/quiqqer', 'street'),
                textCountryZipCity: QUILocale.get(lg, 'customer.panel,information.countryZipCity'),
                textCountry       : QUILocale.get('quiqqer/quiqqer', 'country'),
                textZip           : QUILocale.get('quiqqer/quiqqer', 'zip'),
                textCity          : QUILocale.get('quiqqer/quiqqer', 'city'),
                titleAllocation   : QUILocale.get(lg, 'customer.panel,information.allocation'),
                textGroup         : QUILocale.get(lg, 'customer.panel,information.group'),
                textGroups        : QUILocale.get(lg, 'customer.panel,information.groups'),
                titleCommunication: QUILocale.get(lg, 'customer.panel,information.communication'),
                titleComments     : QUILocale.get(lg, 'customer.panel,information.comments'),

                titleExtra     : QUILocale.get(lg, 'customer.panel,information.extra'),
                textPaymentTerm: QUILocale.get(lg, 'customer.panel,information.paymentTerm'),
                textMail       : QUILocale.get('quiqqer/quiqqer', 'email'),
                textTel        : QUILocale.get('quiqqer/quiqqer', 'tel'),
                textFax        : QUILocale.get('quiqqer/quiqqer', 'fax'),
                textInternet   : QUILocale.get(lg, 'customer.panel,information.extra.homepage')
            }));

            var self = this,
                Form = this.getContent().getElement('form');


            // set data
            Form.elements.userId.value = this.$User.getId();

            if (this.$User.getAttribute('customerId')) {
                Form.elements.customerId.value = this.$User.getAttribute('customerId');
            }

            // groups
            Form.elements.groups.value     = this.$User.getAttribute('usergroup');
            Form.elements.group.value      = this.$User.getAttribute('mainGroup');
            Form.elements.customerId.value = this.$User.getAttribute('customerId');

            // address
            var address = parseInt(this.$User.getAttribute('address'));

            return Countries.getCountries().then(function (countries) {
                var CountrySelect = Form.elements['address-country'];

                for (var code in countries) {
                    if (!countries.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html : countries[code]
                    }).inject(CountrySelect);
                }

                return self.getAddress(address);
            }).then(function (address) {
                if (!address) {
                    return;
                }

                Form.elements['address-salutation'].value = address.salutation;
                Form.elements['address-firstname'].value  = address.firstname;
                Form.elements['address-lastname'].value   = address.lastname;
                // Form.elements['address-company'].value    = address.country;
                Form.elements['address-street_no'].value  = address.street_no;
                Form.elements['address-zip'].value        = address.zip;
                Form.elements['address-country'].value    = address.country;

                try {
                    var CBody = self.getElm().getElement('.customer-information-communication-body');
                    var phone = JSON.decode(address.phone);

                    if (self.$User.getAttribute('address-communication')) {
                        phone = self.$User.getAttribute('address-communication');
                    }

                    var i, len, Row;

                    var rows   = [],
                        tel    = false,
                        fax    = false,
                        mobile = false;

                    for (i = 0, len = phone.length; i < len; i++) {
                        Row = new Element('tr', {
                            'data-type': phone[i].type,
                            html       : '<td>' +
                                '<label class="field-container">' +
                                '   <span class="field-container-item">' + QUILocale.get('quiqqer/quiqqer', phone[i].type) + '</span>' +
                                '   <input name="address-communication" class="field-container-field"/>' +
                                '</label>' +
                                '</td>'
                        });

                        Row.getElement('input').set('value', phone[i].no);
                        Row.getElement('input').set('data-type', phone[i].type);

                        switch (phone[i].type) {
                            case 'tel':
                                tel = true;
                                break;
                            case 'fax':
                                fax = true;
                                break;
                            case 'mobile':
                                mobile = true;
                                break;
                        }

                        rows.push(Row);
                    }

                    if (tel === false) {
                        rows.push(
                            new Element('tr', {
                                'data-type': 'tel',
                                html       : '<td>' +
                                    '<label class="field-container">' +
                                    '   <span class="field-container-item">' + QUILocale.get('quiqqer/quiqqer', 'tel') + '</span>' +
                                    '   <input name="address-communication" data-type="tel" class="field-container-field"/>' +
                                    '</label>' +
                                    '</td>'
                            })
                        );
                    }

                    if (fax === false) {
                        rows.push(
                            new Element('tr', {
                                'data-type': 'fax',
                                html       : '<td>' +
                                    '<label class="field-container">' +
                                    '   <span class="field-container-item">' + QUILocale.get('quiqqer/quiqqer', 'fax') + '</span>' +
                                    '   <input name="address-communication" data-type="fax" class="field-container-field"/>' +
                                    '</label>' +
                                    '</td>'
                            })
                        );
                    }

                    if (mobile === false) {
                        rows.push(
                            new Element('tr', {
                                'data-type': 'mobile',
                                html       : '<td>' +
                                    '<label class="field-container">' +
                                    '   <span class="field-container-item">' + QUILocale.get('quiqqer/quiqqer', 'mobile') + '</span>' +
                                    '   <input name="address-communication" data-type="mobile" class="field-container-field"/>' +
                                    '</label>' +
                                    '</td>'
                            })
                        );
                    }

                    rows.sort(function (a, b) {
                        return a.get('data-type').localeCompare(b.get('data-type')) * -1;
                    });

                    rows.forEach(function (R) {
                        R.inject(CBody);
                    });
                } catch (e) {
                }
            }).then(function () {
                // load comments
                self.getComments().then(function (comments) {
                    require(['package/quiqqer/erp/bin/backend/controls/Comments'], function (Comments) {
                        var Container = self.getContent().getElement('.comments');

                        if (!Container) {
                            return;
                        }

                        Container.set('html', '');

                        comments = comments.reverse();
                        comments = comments.slice(0, 5);

                        var Control = new Comments();
                        Control.inject(Container);
                        Control.unserialize(comments);
                    });
                });

                return QUI.parse(self.getContent());
            });
        },

        /**
         * return all comments for the user
         *
         * @return {Promise}
         */
        getComments: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getComments', resolve, {
                    'package': 'quiqqer/customer',
                    uid      : self.$User.getId()
                });
            });
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
         *
         * @param {Integer} addressId
         * @return {Promise}
         */
        getAddress: function (addressId) {
            addressId = parseInt(addressId);

            return this.$User.getAddressList().then(function (addressList) {
                for (var i = 0, len = addressList.length; i < len; i++) {
                    if (parseInt(addressList[i].id) === addressId) {
                        return addressList[i];
                    }
                }

                return false;
            });
        },

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
         * open the comments
         *
         * @return {Promise}
         */
        $openComments: function () {
            var self = this;

            this.getContent().set('html', '');

            return new Promise(function (resolve) {
                require(['package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments'], function (Comments) {
                    new Comments({
                        userId: self.getAttribute('userId'),
                        events: {
                            onLoad: resolve
                        }
                    }).inject(self.getContent());
                });
            });
        },

        /**
         * open the user information
         *
         * @return {Promise}
         */
        $openUserInformation: function () {
            var self = this;

            this.getContent().set('html', '');

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation'
                ], function (UserInformation) {
                    new UserInformation({
                        userId: self.getAttribute('userId'),
                        events: {
                            onLoad: resolve
                        }
                    }).inject(self.getContent());
                });
            });
        },

        /**
         * open the user properties
         *
         * @return {Promise}
         */
        $openUserProperty: function () {
            var self = this;

            this.getContent().set('html', '');

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties'
                ], function (UserProperties) {
                    new UserProperties({
                        userId: self.getAttribute('userId'),
                        events: {
                            onLoad: resolve
                        }
                    }).inject(self.getContent());
                });
            });
        },

        /**
         * Opens a category panel
         *
         * @param {String} category - name of the category
         */
        $openCategory: function (category) {
            var self = this;

            this.Loader.show();
            this.$categoryUnload();

            this.$hideCategory().then(function () {
                if (category === 'information') {
                    return self.$openInformation();
                }

                if (category === 'addresses') {
                    return self.$openAddressManagement();
                }

                if (category === 'comments') {
                    return self.$openComments();
                }

                if (category === 'userInformation') {
                    return self.$openUserInformation();
                }

                if (category === 'userProperty') {
                    return self.$openUserProperty();
                }

                // load customer category
                return new Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getCategory', function (result) {
                        self.getContent().set('html', '<form>' + result + '</form>');

                        QUI.parse(self.getContent()).then(resolve);
                    }, {
                        'package': 'quiqqer/customer',
                        category : category
                    });
                });
            }).then(function () {
                return self.$showCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * set the form data to the user
         */
        $categoryUnload: function () {
            var Content = this.getContent(),
                Form    = Content.getElement('form');

            if (!Form) {
                return;
            }

            var data = FormUtils.getFormData(Form);
            var com  = Form.getElements('[name="address-communication"]');

            if (typeof data.id !== 'undefined') {
                delete data.id;
            }

            if (com.length) {
                data['address-communication'] = com.map(function (entry) {
                    return {
                        type: entry.get('data-type'),
                        no  : entry.get('value')
                    };
                });
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

        /**
         *
         * @param Category
         */
        $onCustomerCategoryActive: function (Category) {
            this.$openCategory(Category.getAttribute('name'));
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

        //endregion

        //region user events

        /**
         * event on user delete
         *
         * @param {Object} Users - qui/classes/users/Manager
         * @param {Array} uids - user ids, which are deleted
         */
        $onUserDelete: function (Users, uids) {
            var uid = parseInt(this.getAttribute('userId'));

            for (var i = 0, len = uids.length; i < len; i++) {
                if (uid === parseInt(uids[i])) {
                    this.destroy();
                    break;
                }
            }
        }

        //endregion
    });
});
