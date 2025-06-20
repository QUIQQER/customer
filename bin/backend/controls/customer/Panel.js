/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoaded [this] - Fires when Panel has finished intitializing
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/ButtonMultiple',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Confirm',
    'package/quiqqer/countries/bin/Countries',
    'package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow',

    'package/quiqqer/customer/bin/backend/Handler',

    'qui/utils/Form',
    'Users',
    'Locale',
    'Ajax',
    'Packages',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.Information.html',
    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.EditId.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/Panel.css'

], function(QUI, QUIPanel, QUIButton, QUIButtonMultiple, QUILoader, QUIConfirm, Countries, AddressEditWindow, Handler,
    FormUtils, Users, QUILocale, QUIAjax, Packages, Mustache, templateInformation, templateEditId
) {
    'use strict';

    const lg = 'quiqqer/customer';

    let paymentsInstalled = false;
    let shippingInstalled = false;

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/customer/bin/backend/controls/customer/Panel',

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
            '$onCustomerCategoryActive',
            'openUser',
            'deliveryAddressToggle',
            '$onOpenOpenItemsListClick',
            '$onClickSendMail',
            '$onEditCustomerIdClick',
            '$getPagination',
            '$onCommentsPaginationChange',
            '$loadComments'
        ],

        options: {
            icon: 'fa fa-user',
            userId: false,
            showUserButton: true,
            showDeleteButton: true,
            'hide-loader': false
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttribute('#id', 'customer-panel-' + this.getAttribute('userId'));

            this.$User = null;
            this.$userInitAttributes = null;
            this.$customerIdPrefix = '';
            this.$editCustomerNo = false;
            this.$currentCategory = 'information';
            this.$CommentsPagination = null;
            this.$CommentsPage = {
                page: 1,
                limit: 10
            };

            this.$init = true;

            this.addEvents({
                onCreate: this.$onCreate,
                onDestroy: this.$onDestroy,
                onShow: this.$onShow
            });

            Users.addEvent('onDelete', this.$onUserDelete);
        },

        /**
         * the panel on destroy event
         * remove the binded events
         */
        $onDestroy: function() {
            Users.removeEvent('delete', this.$onUserDelete);
        },

        //region actions

        /**
         * alias for update
         *
         * @return {Promise}
         */
        save: function() {
            return this.update();
        },

        /**
         * Saves / Update the user
         */
        update: function() {
            const self = this;

            this.Loader.show();
            this.$categoryUnload();

            return new Promise(function(resolve) {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_customer_save', resolve, {
                    'package': 'quiqqer/customer',
                    userId: self.$User.getId(),
                    data: JSON.encode(self.$User.getAttributes()),
                    onError: resolve
                });
            }).then(function() {
                return self.$refreshTitle();
            }).then(function() {
                // refresh user
                return self.$User.load();
            }).then(function() {
                // search all user panels and refresh it
                const userPanels = QUI.Controls.getByType('controls/users/User');
                const customerId = self.$User.getId();

                for (let i = 0, len = userPanels.length; i < len; i++) {
                    if (userPanels[i].getUser().getId() === customerId) {
                        userPanels[i].refreshDisplay();
                    }
                }

                self.$openCategory(self.$currentCategory);
                self.Loader.hide();
            });
        },

        /**
         * open the use panel
         */
        openUser: function() {
            const self = this;

            require([
                'controls/users/User',
                'utils/Panels'
            ], function(User, Utils) {
                Utils.openPanelInTasks(
                    new User(self.getAttribute('userId'))
                ).then(function() {
                    self.destroy();
                });
            });
        },

        //endregion

        /**
         * event: on create
         */
        $onCreate: function() {
            const self = this;

            if (this.getAttribute('hide-loader')) {
                this.Loader = new QUILoader();
            }

            this.getElm().addClass('quiqqer-customer-panel');

            // buttons
            this.addButton({
                name: 'userSave',
                text: QUILocale.get('quiqqer/core', 'users.user.btn.save'),
                textimage: 'fa fa-save',
                events: {
                    onClick: this.$onSaveClick
                }
            });

            const ExtrasBtn = new QUIButtonMultiple({
                name: 'extra',
                textimage: 'fa fa-caret-down',
                title: QUILocale.get(lg, 'quiqqer.customer.panel.extras.title'),
                events: {
                    onClick: function() {
                        ExtrasBtn.getMenu().then(function(Menu) {
                            let pos = ExtrasBtn.getElm().getPosition(),
                                size = ExtrasBtn.getElm().getSize();

                            Menu.setAttribute('corner', 'topRight');

                            ExtrasBtn.openMenu().then(function() {
                                Menu.setPosition(
                                    pos.x - 135,
                                    pos.y + size.y + 10
                                );
                            });
                        });
                    }
                },
                styles: {
                    'float': 'right'
                }
            });

            if (this.getAttribute('showUserButton')) {
                ExtrasBtn.appendChild({
                    name: 'openUser',
                    text: QUILocale.get(lg, 'quiqqer.customer.panel.openUser'),
                    icon: 'fa fa-user',
                    events: {
                        onClick: this.openUser
                    }
                });
            }

            ExtrasBtn.appendChild({
                text: QUILocale.get(lg, 'quiqqer.customer.panel.openItemsListOutput'),
                icon: 'fa fa-th-list',
                events: {
                    onClick: this.$onOpenOpenItemsListClick
                }
            });

            ExtrasBtn.appendChild({
                text: QUILocale.get(lg, 'quiqqer.customer.panel.sendMail'),
                icon: 'fa fa-envelope',
                events: {
                    onClick: this.$onClickSendMail
                }
            });

            ExtrasBtn.getElm().addClass('quiqqer-customer-panel-extrasbtn');

            if (this.getAttribute('showDeleteButton')) {
                this.addButton({
                    name: 'userDelete',
                    'class': 'quiqqer-customer-delete',
                    title: QUILocale.get('quiqqer/core', 'users.user.btn.delete'),
                    icon: 'fa fa-trash-o',
                    events: {
                        onClick: this.$onDeleteClick
                    },
                    styles: {
                        'float': 'right'
                    }
                });
            }

            this.addButton(ExtrasBtn);


            // categories
            this.addCategory({
                name: 'information',
                text: QUILocale.get(lg, 'quiqqer.customer.panel.general'),
                icon: 'fa fa-file',
                events: {
                    onActive: function() {
                        self.$openCategory('information');
                    }
                }
            });

            this.addCategory({
                name: 'userInformation',
                text: QUILocale.get(lg, 'quiqqer.customer.panel.userInformation'),
                icon: 'fa fa-user',
                events: {
                    onActive: function() {
                        self.$openCategory('userInformation');
                    }
                }
            });

            this.addCategory({
                name: 'userProperty',
                text: QUILocale.get(lg, 'quiqqer.customer.panel.userProperty'),
                icon: 'fa fa-user',
                events: {
                    onActive: function() {
                        self.$openCategory('userProperty');
                    }
                }
            });

            this.addCategory({
                name: 'addresses',
                text: QUILocale.get(lg, 'quiqqer.customer.panel.addresses'),
                icon: 'fa fa-address-card-o',
                events: {
                    onActive: function() {
                        self.$openCategory('addresses');
                    }
                }
            });

            this.addCategory({
                name: 'comments',
                text: QUILocale.get(lg, 'quiqqer.customer.panel.comments'),
                icon: 'fa fa-comments',
                events: {
                    onActive: function() {
                        self.$openCategory('comments');
                    }
                }
            });

            this.addCategory({
                name: 'history',
                text: QUILocale.get(lg, 'quiqqer.customer.panel.history'),
                icon: 'fa fa-history',
                events: {
                    onActive: function() {
                        self.$openCategory('history');
                    }
                }
            });

            this.addCategory({
                name: 'files',
                text: QUILocale.get(lg, 'quiqqer.customer.panel.files'),
                icon: 'fa fa-file-text-o',
                events: {
                    onActive: function() {
                        self.$openCategory('files');
                    }
                }
            });

            this.getContent().setStyle('opacity', 0);

            QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getCategories', function(result) {
                const categories = result.categories;

                for (let i = 0, len = categories.length; i < len; i++) {
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
        $onShow: function() {
            this.Loader.show();

            this.setAttribute('icon', 'fa fa-spinner fa-spin');
            this.refresh();

            const self = this;
            const userId = this.getAttribute('userId');
            const User = Users.get(this.getAttribute('userId'));
            let Loaded = Promise.resolve(User);

            if (!User.isLoaded()) {
                Loaded = User.load();
            }

            // check if user panels exists
            const userPanels = QUI.Controls.getByType('controls/users/User');

            for (let i = 0, len = userPanels.length; i < len; i++) {
                if (userPanels[i].getUser().getId() === userId) {
                    userPanels[i].destroy();
                }
            }

            return Loaded.then(function(User) {
                if (!User.getId()) {
                    // user not found
                    self.destroy();

                    QUI.getMessageHandler().then(function(MH) {
                        MH.addError(
                            QUILocale.get('quiqqer/core', 'exception.lib.user.not.found')
                        );
                    });

                    return;
                }

                self.$User = User;
                self.$userInitAttributes = User.getAttributes();

                // check installed
                return Packages.getPackage('quiqqer/payments').catch(function() {
                    return false;
                });
            }).then(function(paymentPkg) {
                if (paymentPkg) {
                    paymentsInstalled = true;
                }

                return Packages.getPackage('quiqqer/shipping').catch(function() {
                    return false;
                });
            }).then(function(shippingPkg) {
                if (shippingPkg) {
                    shippingInstalled = true;
                }

                return Handler.getCustomerIdPrefix();
            }).then(function(customerIdPrefix) {
                self.$customerIdPrefix = customerIdPrefix;
            }).then(function() {
                return self.$refreshTitle();
            }).then(function() {
                if (!self.$ActiveCat) {
                    self.getCategory('information').click();
                } else {
                    self.fireEvent('loaded', [self]);
                    self.$init = false;

                    self.Loader.hide();
                }
            }).catch(function(Err) {
                // user not found
                self.fireEvent('error', [
                    self,
                    Err
                ]);
                self.destroy();

                console.error(Err);

                QUI.getMessageHandler().then(function(MH) {
                    MH.addError(
                        QUILocale.get('quiqqer/core', 'exception.lib.user.not.found')
                    );
                });
            });
        },

        /**
         * Refresh the panel title
         *
         * @return {Promise}
         */
        $refreshTitle: function() {
            if (this.getAttribute('header') === false) {
                return Promise.resolve();
            }

            const self = this;
            let address = false;

            this.setAttribute('icon', 'fa fa-spinner fa-spin');
            this.refresh();

            if (this.$User.getAttribute('address')) {
                address = this.$User.getAttribute('address');
            } else {
                if (this.$User.getAttribute('quiqqer.erp.address')) {
                    address = this.$User.getAttribute('quiqqer.erp.address');
                }
            }

            let GetAddress;

            if (!address) {
                GetAddress = this.getAddressDefaultAddress();
            } else {
                GetAddress = this.getAddress(address);
            }

            return GetAddress.then(function(addressData) {
                const titleData = [];

                if (self.$User.getAttribute('customerId') !== '') {
                    titleData.push(self.$customerIdPrefix + self.$User.getAttribute('customerId'));
                } else {
                    if (self.$User.getUsername() === self.$User.getName()) {
                        titleData.push(self.$User.getUsername());
                    } else {
                        titleData.push(self.$User.getUsername());
                        titleData.push(self.$User.getName());
                    }
                }

                if (self.$User.getAttribute('email') && self.$User.getAttribute('email') !== '') {
                    titleData.push(self.$User.getAttribute('email'));
                }

                if (addressData && addressData.text !== ' ; ; ' && addressData.text !== '') {
                    titleData.push(addressData.text);
                }

                self.setAttribute('icon', 'fa fa-user');
                self.setAttribute('title', QUILocale.get(lg, 'customer.panel.title', {
                    data: titleData.join(' - ')
                }));

                self.refresh();
            });
        },

        /**
         * Edit customer ID
         */
        $onEditCustomerIdClick: function() {
            const self = this;

            new QUIConfirm({
                maxHeight: 275,
                maxWidth: 450,
                autoclose: false,

                title: QUILocale.get(lg, 'customer.panel.editId.title'),
                icon: 'fa fa-edit',

                cancel_button: {
                    text: false,
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button: {
                    text: QUILocale.get(lg, 'customer.panel.editId.confirm'),
                    textimage: 'icon-ok fa fa-check'
                },
                events: {
                    onOpen: function(Win) {
                        const Content = Win.getContent();

                        Content.addClass('quiqqer-customer-customerNo-edit');

                        Content.set('html', Mustache.render(templateEditId, {
                            customerNoInputHeader: QUILocale.get(lg, 'window.customer.creation.customerNo.inputLabel'),
                            labelPrefix: QUILocale.get(lg, 'window.customer.creation.customerNo.labelPrefix')
                        }));

                        const PrefixInput = Content.getElement('input[name="prefix"]');
                        const CustomerNoInput = Content.getElement('input[name="customerId"]');

                        PrefixInput.value = self.$customerIdPrefix;

                        Win.Loader.show();

                        Handler.getNewCustomerNo().then(function(nextCustomerNo) {
                            if (self.$User.getAttribute('customerId')) {
                                const currentCustomerNo = self.$User.getAttribute('customerId');

                                CustomerNoInput.value = currentCustomerNo;
                                Win.getButton('submit').disable();

                                CustomerNoInput.addEvent('keyup', function() {
                                    if (CustomerNoInput.value.trim() !== currentCustomerNo) {
                                        Win.getButton('submit').enable();
                                    } else {
                                        Win.getButton('submit').disable();
                                    }
                                });
                            } else {
                                CustomerNoInput.value = nextCustomerNo;
                            }

                            Content.getElement('.quiqqer-customer-customerNo-edit-nextId').set(
                                'html',
                                QUILocale.get(lg, 'customer.panel.editId.nextCustomerNo', {
                                    nextCustomerNo: nextCustomerNo
                                })
                            );

                            Win.Loader.hide();
                        }, function() {
                            Win.close();
                        });


                        CustomerNoInput.focus();
                        CustomerNoInput.select();
                    },
                    onSubmit: function(Win) {
                        const CustomerNoInput = Win.getContent().getElement('input[name="customerId"]');
                        const newCustomerNo = CustomerNoInput.value.trim();

                        if (newCustomerNo === '') {
                            CustomerNoInput.focus();
                            return;
                        }

                        Win.Loader.show();

                        Handler.validateCustomerNo(newCustomerNo).then(function() {
                            self.$editCustomerNo = newCustomerNo;

                            self.update().then(function() {
                                self.$editCustomerNo = false;
                                Win.close();
                            }, function() {
                                Win.Loader.hide();
                            });
                        }, function() {
                            Win.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        //region information

        /**
         * opens the user information
         *
         * @return {Promise}
         */
        $openInformation: function() {
            this.Loader.show();

            const Content = this.getContent();

            Content.set('html', Mustache.render(templateInformation, {
                detailsTitle: QUILocale.get(lg, 'customer.panel.information.details'),
                textUserId: QUILocale.get('quiqqer/core', 'user_id'),
                textCustomerId: QUILocale.get(lg, 'customerId'),
                textSalutation: QUILocale.get('quiqqer/core', 'salutation'),
                textCompany: QUILocale.get('quiqqer/core', 'company'),
                textAddressSuffix: QUILocale.get('quiqqer/core', 'address.suffix'),
                textFirstname: QUILocale.get('quiqqer/core', 'firstname'),
                textLastname: QUILocale.get('quiqqer/core', 'lastname'),
                titleAddress: QUILocale.get('quiqqer/core', 'address'),
                textStreet: QUILocale.get('quiqqer/core', 'street'),
                textCountryZipCity: QUILocale.get(lg, 'customer.panel.information.countryZipCity'),
                textCountry: QUILocale.get('quiqqer/core', 'country'),
                textZip: QUILocale.get('quiqqer/core', 'zip'),
                textCity: QUILocale.get('quiqqer/core', 'city'),
                textContactPerson: QUILocale.get(lg, 'customer.panel.information.contactPerson'),
                titleAllocation: QUILocale.get(lg, 'customer.panel.information.allocation'),
                textGroup: QUILocale.get(lg, 'customer.panel.information.group'),
                textGroups: QUILocale.get(lg, 'customer.panel.information.groups'),
                titleCommunication: QUILocale.get(lg, 'customer.panel.information.communication'),
                titleCommentsAndHistory: QUILocale.get(lg, 'customer.panel.information.commentsAndHistory'),

                titleExtra: QUILocale.get(lg, 'customer.panel.information.extra'),
                textPaymentTerm: QUILocale.get(lg, 'customer.panel.information.paymentTerm'),
                textStandardShipping: QUILocale.get(lg, 'customer.panel.information.shipping'),
                textMail: QUILocale.get('quiqqer/core', 'email'),
                textTel: QUILocale.get('quiqqer/core', 'tel'),
                textFax: QUILocale.get('quiqqer/core', 'fax'),
                textInternet: QUILocale.get(lg, 'customer.panel.information.extra.homepage'),

                // payments
                payments: paymentsInstalled,
                textStandardPayment: QUILocale.get(lg, 'customer.panel.information.standard.payments'),

                // shipping
                titleDeliveryAddress: QUILocale.get(lg, 'customer.panel.delivery.address'),
                shipping: shippingInstalled,
                textDeliveryAddressSame: QUILocale.get(lg, 'checkout.panel.delivery.option.same')
            }));

            // Edit customer ID button
            new QUIButton({
                icon: 'fa fa-edit',
                title: QUILocale.get(lg, 'customer.panel.btn.edit_id'),
                events: {
                    onClick: this.$onEditCustomerIdClick
                }
            }).inject(Content.getElement('.customer-information-btn-editId'));

            let UserLoaded = Promise.resolve();

            if (!this.$User) {
                UserLoaded = this.$onShow();
            }

            const self = this,
                Form = Content.getElement('form');

            const checkVal = function(str) {
                if (!str || str === 'false') {
                    return '';
                }

                return str;
            };

            let address, delivery;

            // set data
            return UserLoaded.then(function() {
                Form.elements.userId.value = self.$User.getId();

                if (self.$User.getAttribute('customerId')) {
                    Form.elements.customerId.value =
                        self.$customerIdPrefix + checkVal(self.$User.getAttribute('customerId'));
                }

                // groups
                Form.elements.groups.value = self.$User.getAttribute('usergroup');
                Form.elements.group.value = self.$User.getAttribute('mainGroup');

                Form.elements['quiqqer.erp.customer.website'].value =
                    checkVal(self.$User.getAttribute('quiqqer.erp.customer.website'));

                Form.elements['quiqqer.erp.customer.payment.term'].value =
                    checkVal(self.$User.getAttribute('quiqqer.erp.customer.payment.term'));

                // address
                address = self.$User.getAttribute('address');
                delivery = self.$User.getAttribute('quiqqer.delivery.address');

                if (!delivery && shippingInstalled) {
                    Form.elements['address-delivery'].checked = true;
                    Form.elements['address-delivery'].addEvent('change', self.deliveryAddressToggle);
                    self.deliveryAddressToggle();
                }

                return Countries.getCountries();
            }).then(function(countries) {
                const CountrySelect = Form.elements['address-country'];
                const CountrySelectDelivery = Form.elements['address-delivery-country'];

                for (let code in countries) {
                    if (!countries.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html: countries[code]
                    }).inject(CountrySelect);

                    if (CountrySelectDelivery) {
                        new Element('option', {
                            value: code,
                            html: countries[code]
                        }).inject(CountrySelectDelivery);
                    }
                }

                if (!address) {
                    return self.getAddressDefaultAddress();
                }

                return self.getAddress(address);
            }).then(function(address) {
                if (!address) {
                    return;
                }

                Form.elements['address-salutation'].value = address.salutation;
                Form.elements['address-firstname'].value = address.firstname;
                Form.elements['address-lastname'].value = address.lastname;
                Form.elements['address-street_no'].value = address.street_no;
                Form.elements['address-zip'].value = address.zip;
                Form.elements['address-country'].value = address.country;
                Form.elements['address-city'].value = address.city;
                Form.elements['address-company'].value = address.company;
                Form.elements['address-suffix'].value = address.suffix;

                try {
                    const CBody = self.getElm().getElement('.customer-information-communication-body');
                    let phone = JSON.decode(address.phone);
                    let emails = JSON.decode(address.mail);

                    if (self.$User.getAttribute('address-communication')) {
                        let addressEntries = self.$User.getAttribute('address-communication');

                        phone = addressEntries.filter(function(e) {
                            return e.type !== 'email';
                        });

                        emails = addressEntries.filter(function(e) {
                            return e.type === 'email';
                        }).map(function(e) {
                            return e.no;
                        });
                    }


                    let i, len, Row;

                    let rows = [],
                        tel = false,
                        fax = false,
                        email = false,
                        mobile = false;

                    for (i = 0, len = phone.length; i < len; i++) {
                        Row = new Element('tr', {
                            'data-type': phone[i].type,
                            html: '<td>' +
                                '<label class="field-container">' +
                                '   <span class="field-container-item">' +
                                QUILocale.get('quiqqer/core', phone[i].type) + '</span>' +
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

                    for (i = 0, len = emails.length; i < len; i++) {
                        Row = new Element('tr', {
                            'data-type': 'email',
                            html: '<td>' +
                                '<label class="field-container">' +
                                '   <span class="field-container-item">' +
                                QUILocale.get('quiqqer/core', 'email') + '</span>' +
                                '   <input name="address-communication" data-type="email" type="email" class="field-container-field"/>' +
                                '</label>' +
                                '</td>'
                        });

                        Row.getElement('input').set('value', emails[i]);
                        Row.getElement('input').set('data-type', 'email');
                        rows.push(Row);

                        email = true;
                    }

                    if (tel === false) {
                        rows.push(
                            new Element('tr', {
                                'data-type': 'tel',
                                html: '<td>' +
                                    '<label class="field-container">' +
                                    '   <span class="field-container-item">' +
                                    QUILocale.get('quiqqer/core', 'tel') + '</span>' +
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
                                html: '<td>' +
                                    '<label class="field-container">' +
                                    '   <span class="field-container-item">' +
                                    QUILocale.get('quiqqer/core', 'fax') + '</span>' +
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
                                html: '<td>' +
                                    '<label class="field-container">' +
                                    '   <span class="field-container-item">' +
                                    QUILocale.get('quiqqer/core', 'mobile') + '</span>' +
                                    '   <input name="address-communication" data-type="mobile" class="field-container-field"/>' +
                                    '</label>' +
                                    '</td>'
                            })
                        );
                    }

                    if (email === false) {
                        rows.push(
                            new Element('tr', {
                                'data-type': 'email',
                                html: '<td>' +
                                    '<label class="field-container">' +
                                    '   <span class="field-container-item">' +
                                    QUILocale.get('quiqqer/core', 'email') + '</span>' +
                                    '   <input name="address-communication" data-type="email" type="email" class="field-container-field"/>' +
                                    '</label>' +
                                    '</td>'
                            })
                        );
                    }

                    rows.sort(function(a, b) {
                        return a.get('data-type').localeCompare(b.get('data-type')) * -1;
                    });

                    rows.forEach(function(R) {
                        R.inject(CBody);
                    });
                } catch (e) {
                }
            }).then(function() {
                // delivery address
                if (!delivery) {
                    return;
                }

                return self.getAddress(delivery).then(function(deliveryData) {
                    Form.elements['address-delivery-salutation'].value = deliveryData.salutation;
                    Form.elements['address-delivery-firstname'].value = deliveryData.firstname;
                    Form.elements['address-delivery-lastname'].value = deliveryData.lastname;
                    Form.elements['address-delivery-street_no'].value = deliveryData.street_no;
                    Form.elements['address-delivery-zip'].value = deliveryData.zip;
                    Form.elements['address-delivery-country'].value = deliveryData.country;
                    Form.elements['address-delivery-city'].value = deliveryData.city;
                });
            }).then(function() {
                // load comments
                return QUI.parse(self.getContent());
            }).then(function() {
                // load pagination
                return self.$getPagination();
            }).then(function(paginationHtml) {
                const Content = self.getContent();
                const PaginationContainer = Content.getElement('.comments-pagination');

                PaginationContainer.set('html', paginationHtml);

                if (paginationHtml === '') {
                    PaginationContainer.setStyle('display', 'none');
                } else {
                    PaginationContainer.setStyle('display', null);
                }

                return QUI.parse(PaginationContainer).then(function() {
                    if (paginationHtml) {
                        self.$CommentsPagination = QUI.Controls.getById(
                            Content.getElement(
                                '[data-qui="package/quiqqer/controls/bin/navigating/Pagination"]'
                            ).get('data-quiid')
                        );

                        self.$CommentsPagination.addEvents({
                            onChange: self.$onCommentsPaginationChange
                        });
                    }

                    return self.$loadComments();
                });
            }).then(function() {
                // PAYMENTS
                if (paymentsInstalled === false) {
                    return;
                }

                const PaymentSelect = self.getContent().getElement('[name="quiqqer.erp.standard.payment"]'),
                    lang = QUILocale.getCurrent();

                if (!PaymentSelect) {
                    return;
                }

                // standard payment
                return new Promise(function(resolve) {
                    PaymentSelect.set('html', '');

                    new Element('option', {
                        value: '',
                        html: '---'
                    }).inject(PaymentSelect);

                    require([
                        'package/quiqqer/payments/bin/backend/Payments'
                    ], function(Payments) {
                        Payments.getPayments().then(function(payments) {
                            let i, len, text;

                            // payment sort
                            let current = QUILocale.getCurrent();

                            payments.sort((a, b) => {
                                let titleA = a.title[current] ? a.title[current].toLowerCase() : '';
                                let titleB = b.title[current] ? b.title[current].toLowerCase() : '';

                                if (titleA < titleB) {
                                    return -1;
                                }

                                if (titleA > titleB) {
                                    return 1;
                                }

                                return 0;
                            });

                            for (i = 0, len = payments.length; i < len; i++) {
                                text = '';

                                if (typeof payments[i].title === 'string') {
                                    text = payments[i].title;
                                } else {
                                    if (typeof payments[i].title[lang] !== 'undefined') {
                                        text = payments[i].title[lang];
                                    } else {
                                        if (typeof payments[i].title.en === 'undefined') {
                                            text = payments[i].title.en;
                                        }
                                    }
                                }

                                new Element('option', {
                                    value: payments[i].id,
                                    html: text
                                }).inject(PaymentSelect);
                            }

                            if (self.$User.getAttribute('quiqqer.erp.standard.payment')) {
                                PaymentSelect.value = self.$User.getAttribute('quiqqer.erp.standard.payment');
                            }
                        }).then(resolve);
                    });
                });
            }).then(function() {
                if (!shippingInstalled) {
                    return;
                }

                return new Promise(function(resolve) {
                    require(['package/quiqqer/shipping/bin/backend/Shipping'], function(Shipping) {
                        Shipping.getShippingList().then(function(shippingList) {
                            const ShippingSelect = self.getContent().getElement(
                                '[name="quiqqer.erp.standard.shippingType"]');

                            for (let i = 0, len = shippingList.length; i < len; i++) {
                                new Element('option', {
                                    html: shippingList[i].currentTitle + ' (' + shippingList[i].currentWorkingTitle +
                                        ')',
                                    value: shippingList[i].id
                                }).inject(ShippingSelect);
                            }

                            ShippingSelect.value = self.$User.getAttribute('quiqqer.erp.standard.shippingType');

                            resolve();
                        });
                    });
                });
            }).then(function() {
                return self.$refreshContactAddressList();
            }).then(function() {
                const Select = Form.elements['quiqqer.erp.customer.contact.person'],
                    Button = self.getElm().getElement('[name="edit-contact-person"]');

                if (self.$User.getAttribute('quiqqer.erp.customer.contact.person')) {
                    Select.value = self.$User.getAttribute('quiqqer.erp.customer.contact.person');
                }

                Button.disabled = false;

                Select.addEvent('change', function() {
                    if (Select.value === '') {
                        Button.set('html', '<span class="fa fa-plus"></span>');
                        return;
                    }

                    Button.set('html', '<span class="fa fa-edit"></span>');
                });

                Select.fireEvent('change');

                Button.addEvent('click', function(e) {
                    e.stop();

                    // create
                    if (Select.value === '') {
                        // add new address
                        Button.set('html', '<span class="fa fa-spinner fa-spin"></span>');

                        require(['package/quiqqer/customer/bin/backend/Handler'], function(Handler) {
                            Handler.addAddressToCustomer(self.$User.getId()).then(function(addressId) {
                                new AddressEditWindow({
                                    addressId: addressId,
                                    events: {
                                        onSubmit: function() {
                                            // refresh list
                                            self.$User.load().then(function() {
                                                return self.$refreshContactAddressList();
                                            }).then(function() {
                                                Select.value = addressId;
                                                Button.set('html', '<span class="fa fa-edit"></span>');
                                            });
                                        }
                                    }
                                }).open();
                            });
                        });

                        return;
                    }

                    const addressId = Select.value;

                    new AddressEditWindow({
                        addressId: addressId,
                        events: {
                            onSubmit: function() {
                                // refresh list
                                self.$User.load().then(function() {
                                    return self.$refreshContactAddressList();
                                }).then(function() {
                                    Select.value = addressId;
                                    Button.set('html', '<span class="fa fa-edit"></span>');
                                });
                            }
                        }
                    }).open();
                });
            }).then(function() {
                if (self.$init) {
                    self.fireEvent('loaded', [self]);
                    self.$init = false;
                }

                self.Loader.hide();
            });
        },

        /**
         * Edit an address
         * - opens the edit address window
         *
         * @param addressId
         * @return {Promise}
         */
        editAddress: function(addressId) {
            const self = this;

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow'
            ], function(AddressEditWindow) {
                new AddressEditWindow({
                    addressId: addressId,
                    events: {
                        onSubmit: function() {
                            self.Loader.show();

                            self.refreshAddressLists().then(function() {
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
        createAddress: function() {
            const self = this;

            return new Promise(function(resolve) {
                QUIAjax.post('ajax_users_address_save', resolve, {
                    uid: self.$User.getId(),
                    aid: 0,
                    data: '[]'
                });
            });
        },

        /**
         * Refresh the address lists
         *
         * @return {Promise}
         */
        refreshAddressLists: function() {
            this.$User.$addresses = false; // workaround for refresh

            const self = this;
            const DefaultAddress = this.getContent().getElement('[name="address"]');
            const InvoiceAddress = this.getContent().getElement('[name="quiqqer.erp.address"]');
            const DeliveryAddress = this.getContent().getElement('[name="quiqqer.delivery.address"]');

            // set addresses
            return this.$User.getAddressList().then(function(addressList) {
                DefaultAddress.innerHTML = '';
                InvoiceAddress.innerHTML = '';
                DeliveryAddress.innerHTML = '';

                new Element('option', {
                    html: '',
                    value: ''
                }).inject(DefaultAddress);

                new Element('option', {
                    html: '',
                    value: ''
                }).inject(InvoiceAddress);

                new Element('option', {
                    html: '',
                    value: ''
                }).inject(DeliveryAddress);

                for (let i = 0, len = addressList.length; i < len; i++) {
                    new Element('option', {
                        html: addressList[i].text,
                        value: addressList[i].id
                    }).inject(DefaultAddress);

                    new Element('option', {
                        html: addressList[i].text,
                        value: addressList[i].id
                    }).inject(InvoiceAddress);

                    new Element('option', {
                        html: addressList[i].text,
                        value: addressList[i].id
                    }).inject(DeliveryAddress);
                }

                let attributes = self.$User.getAttributes(),
                    address = attributes.address,
                    invoiceAddress = attributes['quiqqer.erp.address'],
                    deliveryAddress = attributes['quiqqer.delivery.address'];

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
         * @param {Integer|String} addressId
         * @return {Promise}
         */
        getAddress: function(addressId) {
            return this.$User.getAddressList().then(function(addressList) {
                for (let i = 0, len = addressList.length; i < len; i++) {
                    if (addressList[i].uuid === addressId) {
                        return addressList[i];
                    }

                    if (parseInt(addressList[i].id) === parseInt(addressId)) {
                        return addressList[i];
                    }
                }

                return false;
            });
        },

        /**
         * Return the default address
         *
         * @return {Promise}
         */
        getAddressDefaultAddress: function() {
            const self = this;

            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getAddress', resolve, {
                    'package': 'quiqqer/customer',
                    userId: self.$User.getId()
                });
            });
        },

        /**
         * open the address management
         *
         * @return {Promise}
         */
        $openAddressManagement: function() {
            const self = this;

            this.getContent().set('html', '');

            return new Promise(function(resolve) {
                require(['package/quiqqer/customer/bin/backend/controls/customer/AddressGrid'], function(AddressGrid) {
                    const Instance = new AddressGrid({
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
        $openComments: function() {
            const self = this;

            this.getContent().set('html', '');

            return new Promise(function(resolve) {
                require(['package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments'], function(Comments) {
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
         * open the history
         *
         * @return {Promise}
         */
        $openHistory: function() {
            const self = this;

            this.getContent().set('html', '');

            return new Promise(function(resolve) {
                require(['package/quiqqer/customer/bin/backend/controls/customer/Panel.History'], function(Comments) {
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
        $openUserInformation: function() {
            const self = this;

            this.getContent().set('html', '');

            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation'
                ], function(UserInformation) {
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
        $openUserProperty: function() {
            const self = this;

            this.getContent().set('html', '');

            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties'
                ], function(UserProperties) {
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
         * open the user files
         *
         * @return {Promise}
         */
        $openFiles: function() {
            const self = this;

            this.getContent().set('html', '');

            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles'
                ], function(UserFiles) {
                    new UserFiles({
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
        $openCategory: function(category) {
            this.Loader.show();
            this.$categoryUnload();
            this.getBody().setStyle('padding', 20);

            this.$hideCategory().then(() => {
                this.$currentCategory = category;

                if (category === 'information') {
                    return this.$openInformation();
                }

                if (category === 'addresses') {
                    return this.$openAddressManagement();
                }

                if (category === 'comments') {
                    this.getBody().setStyle('padding', 0);

                    return this.$openComments();
                }

                if (category === 'history') {
                    this.getBody().setStyle('padding', 0);

                    return this.$openHistory();
                }

                if (category === 'userInformation') {
                    return this.$openUserInformation();
                }

                if (category === 'userProperty') {
                    return this.$openUserProperty();
                }

                if (category === 'files') {
                    return this.$openFiles();
                }

                // load customer category
                return new Promise((resolve) => {
                    QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getCategory', (result) => {
                        this.getContent().set('html', '<form>' + result + '</form>');

                        QUI.parse(this.getContent()).then(() => {
                            return new Promise((resolve) => {
                                require(['utils/Controls'], (ControlUtils) => {
                                    ControlUtils.parse(this.getContent()).then(resolve);
                                });
                            });
                        }).then(resolve);
                    }, {
                        'package': 'quiqqer/customer',
                        category: category
                    });
                });
            }).then(() => {
                return this.$showCategory();
            }).then(() => {
                this.Loader.hide();
            });
        },

        /**
         * set the form data to the user
         */
        $categoryUnload: function() {
            const Content = this.getContent(),
                Form = Content.getElement('form');

            if (!Form) {
                return;
            }

            const data = FormUtils.getFormData(Form);
            const com = Form.getElements('[name="address-communication"]');

            if (typeof data.id !== 'undefined') {
                delete data.id;
            }

            if (com.length) {
                data['address-communication'] = com.map(function(entry) {
                    return {
                        type: entry.get('data-type'),
                        no: entry.get('value')
                    };
                });
            }

            // Only edit customer no. explicitly
            if (this.$editCustomerNo) {
                data.customerId = this.$editCustomerNo;
            } else {
                if ('customerId' in data) {
                    delete data.customerId;
                }
            }

            this.$User.setAttributes(data);
        },

        /**
         * hide category
         *
         * @return {Promise}
         */
        $hideCategory: function() {
            let Content = this.getContent(),
                Form = Content.getElement('form');

            if (!Form) {
                Form = Content.getFirst();
            }

            return new Promise(function(resolve) {
                if (!Form) {
                    resolve();
                    return;
                }

                Form.setStyle('position', 'relative');

                moofx(Form).animate({
                    opacity: 0,
                    top: -50
                }, {
                    duration: 250,
                    callback: function() {
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
        $showCategory: function() {
            let Content = this.getContent(),
                Form = Content.getElement('form');

            if (!Form) {
                Form = Content.getFirst();
            }

            return new Promise(function(resolve) {
                Form.setStyle('position', 'relative');
                Form.setStyle('top', -50);
                Form.setStyle('opacity', 0);

                Content.setStyle('opacity', 1);

                moofx(Form).animate({
                    opacity: 1,
                    top: 0
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
        $onCustomerCategoryActive: function(Category) {
            this.$openCategory(Category.getAttribute('name'));
        },

        //endregion

        //region button actions

        /**
         * event: on address edit click
         *
         * @param event
         */
        $clickEditAddress: function(event) {
            let Target = event.target;

            if (Target.nodeName !== 'BUTTON') {
                Target = Target.getParent('button');
            }

            const Select = Target.getPrevious();

            if (Select.value === '') {
                return;
            }

            this.editAddress(Select.value);
        },

        /**
         * event: on save click
         */
        $onSaveClick: function() {
            this.Loader.show();
            this.update().then(function() {
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * event: on delete click
         */
        $onDeleteClick: function() {
            const uid = this.$User.getId();

            new QUIConfirm({
                name: 'DeleteUser',
                icon: 'fa fa-trash-o',
                texticon: 'fa fa-trash-o',
                title: QUILocale.get('quiqqer/core', 'users.user.window.delete.title'),
                text: QUILocale.get('quiqqer/core', 'users.user.window.delete.text', {
                    userid: this.$User.getId(),
                    username: this.$User.getName()
                }),
                information: QUILocale.get('quiqqer/core', 'users.user.window.delete.information'),
                maxWidth: 600,
                maxHeight: 400,
                autoclose: false,
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();
                        Users.deleteUsers([uid]).then(function() {
                            Win.close();
                        });
                    }
                }
            }).open();
        },

        /**
         * User clicks on "open OpenItemList" btn
         */
        $onOpenOpenItemsListClick: function() {
            const self = this;

            require([
                'package/quiqqer/erp/bin/backend/controls/OutputDialog'
            ], function(OutputDialog) {
                new OutputDialog({
                    entityId: self.getAttribute('userId'),
                    entityType: 'OpenItemsList'
                }).open();
            });
        },

        /**
         * Open "send mail to user" popup
         */
        $onClickSendMail: function() {
            const self = this;

            require(['controls/users/mail/SendUserMail'], function(SendUserMail) {
                new SendUserMail({
                    userId: self.getAttribute('userId')
                }).open();
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
        $onUserDelete: function(Users, uids) {
            const uid = this.getAttribute('userId');

            for (let i = 0, len = uids.length; i < len; i++) {
                if (uid === uids[i]) {
                    this.destroy();
                    break;
                }
            }
        },

        //endregion

        //region delivery

        /**
         * Toggle the delivery address
         */
        deliveryAddressToggle: function() {
            if (!shippingInstalled) {
                return;
            }

            const Checkbox = this.getContent().getElement('[name="address-delivery"]');

            if (Checkbox.checked) {
                this.deliveryAddressClose();
            } else {
                this.deliveryAddressOpen();
            }
        },

        /**
         * opens the delivery address
         */
        deliveryAddressOpen: function() {
            if (!shippingInstalled) {
                return;
            }

            const Table = this.getContent().getElement('.delivery-address-table');

            if (!Table) {
                return;
            }

            Table.getElements('tr').forEach(function(Row) {
                if (Row.getElement('input') && Row.getElement('input').name !== 'address-delivery') {
                    Row.setStyle('display', null);
                }
            });
        },

        /**
         * Close the delivery address
         */
        deliveryAddressClose: function() {
            if (!shippingInstalled) {
                return;
            }

            const Table = this.getContent().getElement('.delivery-address-table');

            if (!Table) {
                return;
            }

            Table.getElements('tr').forEach(function(Row) {
                if (Row.getElement('input') && Row.getElement('input').name !== 'address-delivery') {
                    Row.setStyle('display', 'none');
                }
            });
        },

        /**
         * refresh the contact address list
         *
         * @return {Promise<void>}
         */
        $refreshContactAddressList: function() {
            const self = this;

            return this.$User.getAddressList().then(function(addresses) {
                if (!self.getContent().getElement('form')) {
                    return;
                }

                const Form = self.getContent().getElement('form'),
                    Select = Form.elements['quiqqer.erp.customer.contact.person'];

                Select.set('html', '');

                new Element('option', {
                    value: '',
                    html: ''
                }).inject(Select);

                for (let i = 0, len = addresses.length; i < len; i++) {
                    new Element('option', {
                        value: addresses[i].id,
                        html: addresses[i].text
                    }).inject(Select);
                }
            });
        },

        //endregion

        // region pagination

        /**
         * return all comments for the user
         *
         * @return {Promise}
         */
        getComments: function() {
            const self = this;

            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getCommentsAndHistory', resolve, {
                    'package': 'quiqqer/customer',
                    uid: self.$User.getId(),
                    page: self.$CommentsPage.page,
                    limit: self.$CommentsPage.limit
                });
            });
        },

        /**
         * Load comments and history
         *
         * @return {Promise}
         */
        $loadComments()
        {
            const self = this;

            this.Loader.show();

            return this.getComments().then(function(comments) {
                require(['package/quiqqer/erp/bin/backend/controls/Comments'], function(Comments) {
                    const Container = self.getContent().getElement('.comments');

                    if (!Container) {
                        return;
                    }

                    Container.set('html', '');

                    const Control = new Comments();
                    Control.inject(Container);
                    Control.unserialize(comments);

                    self.Loader.hide();
                });
            });
        },

        /**
         * If pagination on comments change
         *
         * @param {Object} Control
         * @param {Object} SheetElm
         * @param {Object} Page
         */
        $onCommentsPaginationChange: function(Control, SheetElm, Page) {
            this.$CommentsPage = Page;
            this.$loadComments();
        },

        /**
         * Get HTML of pagination control
         *
         * @return {Promise}
         */
        $getPagination: function() {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getPagination', resolve, {
                    'package': 'quiqqer/customer',
                    uid: self.getAttribute('userId'),
                    onError: reject
                });
            });
        }

        // endregion
    });
});
