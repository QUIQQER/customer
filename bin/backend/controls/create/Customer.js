/**
 * @module package/quiqqer/customer/bin/backend/controls/create/Customer
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/create/Customer', [

    'qui/QUI',
    'qui/controls/Control',

    'package/quiqqer/countries/bin/Countries',
    'package/quiqqer/customer/bin/backend/Handler',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/create/Customer.html',

    'css!package/quiqqer/customer/bin/backend/controls/create/Customer.css'

], function(QUI, QUIControl, Countries, Handler, QUILocale, QUIAjax, Mustache, template) {
    'use strict';

    const lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/customer/bin/backend/controls/create/Customer',

        Binds: [
            '$onInject',
            'next',
            'previous'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$Container = null;
            this.$List = null;
            this.$Form = null;
            this.$Steps = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * event: on create
         *
         * @return {HTMLDivElement}
         */
        create: function() {
            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-customer-create');
            this.$Elm.set('data-qui', 'package/quiqqer/customer/bin/backend/controls/create/Customer');

            this.$Elm.set('html', Mustache.render(template, {
                customerNoHeader: QUILocale.get(lg, 'window.customer.creation.customerNo.title'),
                customerNoText: QUILocale.get(lg, 'window.customer.creation.customerNo.text'),
                customerNoInputHeader: QUILocale.get(lg, 'window.customer.creation.customerNo.inputLabel'),
                customerDataHeader: QUILocale.get(lg, 'window.customer.creation.dataHeader.title'),
                customerDataText: QUILocale.get(lg, 'window.customer.creation.dataHeader.text'),
                customerGroupsHeader: QUILocale.get(lg, 'window.customer.creation.groups.title'),
                customerGroupsText: QUILocale.get(lg, 'window.customer.creation.groups.text'),
                labelPrefix: QUILocale.get(lg, 'window.customer.creation.customerNo.labelPrefix'),
                textIsNettoBruttoUser: QUILocale.get(lg, 'customer.user.information.textBruttoNetto'),
                textNetto: QUILocale.get('quiqqer/erp', 'user.settings.userNettoStatus.netto'),
                textBrutto: QUILocale.get('quiqqer/erp', 'user.settings.userNettoStatus.brutto'),

                textAddressCompany: QUILocale.get('quiqqer/core', 'company'),
                textAddressSalutation: QUILocale.get('quiqqer/core', 'salutation'),
                textAddressFirstname: QUILocale.get('quiqqer/core', 'firstname'),
                textAddressLastname: QUILocale.get('quiqqer/core', 'lastname'),
                textAddressStreet: QUILocale.get('quiqqer/core', 'street'),
                textAddressZIP: QUILocale.get('quiqqer/core', 'zip'),
                textAddressCity: QUILocale.get('quiqqer/core', 'city'),
                textAddressCountry: QUILocale.get('quiqqer/core', 'country'),
                textAddressSuffix: QUILocale.get('quiqqer/core', 'address.suffix'),

                textGroup: QUILocale.get(lg, 'window.customer.creation.group'),
                textGroups: QUILocale.get(lg, 'window.customer.creation.groups'),
                previousButton: QUILocale.get(lg, 'window.customer.creation.previous'),
                nextButton: QUILocale.get(lg, 'window.customer.creation.next')
            }));

            this.$Form = this.$Elm.getElement('form');

            // key events
            const self = this;
            const CustomerId = this.$Elm.getElement('[name="customerId"]');
            const Company = this.$Elm.getElement('[name="address-company"]');
            const Country = this.$Elm.getElement('[name="address-country"]');

            CustomerId.addEvent('keydown', function(event) {
                if (event.key === 'tab') {
                    event.stop();
                    self.next().then(function() {
                        Company.focus();
                    });
                }
            });

            CustomerId.addEvent('keyup', function(event) {
                if (event.key === 'enter') {
                    event.stop();
                    self.next();
                }
            });

            Company.addEvent('keydown', function(event) {
                if (event.key === 'tab' && event.shift) {
                    event.stop();
                    self.previous().then(function() {
                        CustomerId.focus();
                    });
                }
            });

            Country.addEvent('keydown', function(event) {
                if (event.key === 'tab') {
                    event.stop();
                    self.next();
                }
            });

            this.$Container = this.$Elm.getElement('.quiqqer-customer-create-container');
            this.$List = this.$Elm.getElement('.quiqqer-customer-create-container ul');
            this.$Next = this.$Elm.getElement('[name="next"]');
            this.$Previous = this.$Elm.getElement('[name="previous"]');
            this.$Steps = this.$Elm.getElement('.quiqqer-customer-create-button-steps');

            this.$Next.addEvent('click', this.next);
            this.$Previous.addEvent('click', this.previous);
            this.refreshStepDisplay();

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function() {
            const self = this;
            const Group = this.$Elm.getElement('[name="group"]');

            Countries.getCountries().then(function(countries) {
                const CountrySelect = self.$Elm.getElement('[name="address-country"]');

                for (let code in countries) {
                    if (!countries.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html: countries[code]
                    }).inject(CountrySelect);
                }

                if (QUIQQER_CONFIG.globals.country) {
                    CountrySelect.value = QUIQQER_CONFIG.globals.country;
                }
            }).then(() => {
                const Select = this.$Elm.getElement('[name="quiqqer.erp.isNettoUser"]')

                return new Promise((resolve) => {
                    QUIAjax.get('package_quiqqer_customer_ajax_backend_getBusinessType', (businessType) => {
                        switch (businessType.toLowerCase()) {
                            case 'b2b':
                            case 'b2b-b2c':
                                Select.value = '1';
                                break;

                            case 'b2c':
                            case 'b2c-b2b':
                                Select.value = '2';
                                break;
                        }

                        resolve();
                    }, {
                        'package': 'quiqqer/customer',
                    });
                });
            }).then(function() {
                return QUI.parse(self.$Elm);
            }).then(function() {
                const GroupControl = QUI.Controls.getById(Group.get('data-quiid'));

                GroupControl.disable();
                self.showCustomerNumber();
            });
        },

        /**
         * Create the customer
         */
        createCustomer: function() {
            const self = this;
            const elements = this.$Form.elements;
            const customerId = elements.customerId.value;
            const groups = elements.groups.value.split(',');

            const address = {
                'salutation': elements['address-salutation'].value,
                'firstname': elements['address-firstname'].value,
                'lastname': elements['address-lastname'].value,
                'company': elements['address-company'].value,
                'street_no': elements['address-street_no'].value,
                'zip': elements['address-zip'].value,
                'city': elements['address-city'].value,
                'country': elements['address-country'].value,
                'suffix': elements['address-suffix'].value
            };


            this.fireEvent('createCustomerBegin', [this]);

            QUIAjax.post('package_quiqqer_customer_ajax_backend_create_createCustomer', function(customerId) {
                self.fireEvent('createCustomerEnd', [self, customerId]);
            }, {
                'package': 'quiqqer/customer',
                customerId: customerId,
                address: JSON.encode(address),
                groups: JSON.encode(groups),
                attributes: JSON.encode({
                    'quiqqer.erp.isNettoUser': this.$Elm.getElement('[name="quiqqer.erp.isNettoUser"]').value
                })
            });
        },

        /**
         * Show next step
         */
        next: function() {
            if (this.$Next.get('data-last')) {
                return this.createCustomer();
            }

            const self = this;
            const steps = this.$List.getElements('li');
            const pos = this.$List.getPosition(this.$Container);
            const top = pos.y;

            const height = this.$Container.getSize().y;
            const scrollHeight = this.$Container.getScrollSize().y;
            const newTop = this.$roundToStepPos(top - height);

            let step = 1;

            if ((top * -1) / height) {
                step = Math.round(((top * -1) / height) + 1);
            }

            // change last step button
            if (newTop - height <= scrollHeight * -1) {
                this.$Next.set('html', QUILocale.get(lg, 'window.customer.creation.create'));
                this.$Next.set('data-last', 1);
            }

            // check if last step
            if (newTop <= steps.length * height * -1) {
                return Promise.resolve();
            }

            return new Promise(function(resolve) {
                const checkPromises = [];

                if (step === 1) {
                    const elements = self.$Form.elements;
                    const customerId = elements.customerId.value;

                    checkPromises.push(Handler.validateCustomerNo(customerId));
                }

                Promise.all(checkPromises).then(function() {
                    moofx(self.$List).animate({
                        top: newTop
                    }, {
                        callback: function() {
                            self.refreshStepDisplay();
                            resolve();
                        }
                    });
                }, function() {
                    resolve();
                });
            });
        },

        /**
         * Previous next step
         */
        previous: function() {
            const self = this;
            const pos = this.$List.getPosition(this.$Container);
            const top = pos.y;

            const height = this.$Container.getSize().y;
            let newTop = this.$roundToStepPos(top + height);

            this.$Next.set('html', QUILocale.get(lg, 'window.customer.creation.next'));
            this.$Next.set('data-last', null);

            if (newTop > 0) {
                newTop = 0;
            }

            return new Promise(function(resolve) {
                moofx(self.$List).animate({
                    top: newTop
                }, {
                    callback: function() {
                        self.refreshStepDisplay();
                        resolve();
                    }
                });
            });
        },

        /**
         * refresh the step display
         */
        refreshStepDisplay: function() {
            let step = 1;
            const steps = this.$List.getElements('li');
            const pos = this.$List.getPosition(this.$Container);
            const top = pos.y;
            const height = this.$Container.getSize().y;

            if ((top * -1) / height) {
                step = Math.round(((top * -1) / height) + 1);
            }

            switch (step) {
                case 1:
                    this.$Container.getElement('input[name="customerId"]').focus();
                    break;

                case 2:
                    this.$Container.getElement('input[name="address-company"]').focus();
                    break;
            }

            this.$Steps.set('html', QUILocale.get(lg, 'customer.create.steps', {
                step: step,
                max: steps.length
            }));
        },

        /**
         *
         * @param currentPos
         * @return {number}
         */
        $roundToStepPos: function(currentPos) {
            const height = this.$Container.getSize().y;
            const pos = Math.round(currentPos / height) * -1;

            return pos * height * -1;
        },

        /**
         * Show the customer number step
         */
        showCustomerNumber: function() {
            const self = this;

            this.$Next.disabled = true;

            Promise.all([
                Handler.getNewCustomerNo(),
                Handler.getCustomerIdPrefix()
            ]).then(function(result) {
                const Input = self.$Elm.getElement('input[name="customerId"]');
                const InputPrefix = self.$Elm.getElement('input[name="prefix"]');

                if (result[1]) {
                    InputPrefix.value = result[1];
                } else {
                    InputPrefix.setStyle('display', 'none');
                }

                Input.value = result[0];
                Input.focus();

                self.$Next.disabled = false;
                self.fireEvent('load', [self]);
            });
        }
    });
});
