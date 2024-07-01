/**
 * Makes a customer input field to a field selection field
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/Select
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onAddItem [ this, id ]
 * @event onChange [ this ]
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Select', [

    'qui/QUI',
    'qui/controls/elements/Select',
    'Locale',
    'Ajax',

    'css!package/quiqqer/customer/bin/backend/controls/customer/Select.css'

], function(QUIControl, QUIElementSelect, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/customer';

    /**
     * @param {Object} options
     * @param {HTMLInputElement} [Input]  - (optional), if no input given, one would be created
     *
     * @memberof! <global>
     */
    return new Class({

        Extends: QUIElementSelect,
        Type: 'package/quiqqer/customer/bin/backend/controls/customer/Select',

        Binds: [
            '$onCreate',
            '$onSearchButtonClick',
            'openCustomerSearch',
            'editCustomer',
            'createCustomer',
            'userSearch'
        ],

        options: {
            showAddressName: true
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttribute('Search', this.userSearch);
            this.setAttribute('icon', 'fa fa-user-o');
            this.setAttribute('child', 'package/quiqqer/customer/bin/backend/controls/customer/SelectItem');

            this.setAttribute(
                'placeholder',
                QUILocale.get(lg, 'control.users.select.search.field.placeholder')
            );

            this.addEvents({
                onCreate: this.$onCreate
            });
        },

        $onCreate: function() {
            this.getElm().addClass('quiqqer-customer-select');

            this.$SearchButton.setAttribute('menuCorner', 'topRight');

            this.$SearchButton.appendChild({
                text: QUILocale.get(lg, 'customer.select.button.search'),
                name: 'search',
                icon: 'fa fa-search',
                events: {
                    click: this.openCustomerSearch
                }
            });

            this.$SearchButton.appendChild({
                text: QUILocale.get(lg, 'customer.select.button.create'),
                name: 'create',
                icon: 'fa fa-plus',
                events: {
                    click: this.createCustomer
                }
            });

            this.$SearchButton.appendChild({
                text: QUILocale.get(lg, 'customer.select.button.edit'),
                name: 'edit',
                icon: 'fa fa-edit',
                disabled: true,
                events: {
                    click: this.editCustomer
                }
            });

            const Search = this.$SearchButton.getChildren().filter((Instance) => {
                return Instance.getAttribute('name') === 'search';
            })[0];


            const Edit = this.$SearchButton.getChildren().filter((Instance) => {
                return Instance.getAttribute('name') === 'edit';
            })[0];

            this.$SearchButton.getContextMenu((Menu) => {
                Menu.setAttribute('menuCorner', 'topRight');
                Menu.addEvent('show', () => {
                    if (this.getValue()) {
                        Edit.enable();
                        Search.setAttribute('text', QUILocale.get(lg, 'customer.select.button.replace'));
                    } else {
                        Edit.disable();
                        Search.setAttribute('text', QUILocale.get(lg, 'customer.select.button.create'));
                    }

                    Menu.getElm().setStyle('left', Menu.getElm().getPosition().x + 15);
                });
            });
        },

        /**
         * Execute the search
         *
         * @param {String} value
         * @returns {Promise}
         */
        userSearch: function(value) {
            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_search', function(result) {
                    const data = [],
                        userResult = result.data;

                    for (let i = 0, len = userResult.length; i < len; i++) {
                        data.push({
                            id: userResult[i].user_uuid,
                            title: userResult[i].username + ': ' + userResult[i].address_display,
                            icon: 'fa fa fa-user-o'
                        });
                    }

                    resolve(data);
                }, {
                    'package': 'quiqqer/customer',
                    // search   : value,
                    fields: false,
                    params: JSON.encode({
                        perPage: 5,
                        onlyCustomer: true,
                        search: value
                    })
                });
            });
        },

        /**
         * event : on search click
         *
         * @param {Object} Select
         * @param {Object} Btn
         */
        $onSearchButtonClick: function(Select, Btn) {
            const self = this,
                oldIcon = Btn.getAttribute('icon');

            Btn.setAttribute('icon', 'fa fa-spinner fa-spin');
            Btn.disable();

            require([
                'package/quiqqer/customer/bin/backend/controls/AdministrationWindow'
            ], (Window) => {
                new Window({
                    autoclose: true,
                    multiple: self.getAttribute('multiple'),
                    search: self.getAttribute('search'),
                    searchSettings: self.getAttribute('searchSettings'),
                    customerId: self.getValue(),
                    events: {
                        onSubmit: function(Win, userIds) {
                            for (let i = 0, len = userIds.length; i < len; i++) {
                                self.addItem(userIds[i]);
                            }
                        }
                    }
                }).open();

                Btn.setAttribute('icon', oldIcon);
                Btn.enable();
            });
        },

        /**
         * Opens the customer search window
         */
        openCustomerSearch: function() {
            const oldIcon = this.$SearchButton.getAttribute('icon');

            this.$SearchButton.setAttribute('icon', 'fa fa-spinner fa-spin');
            this.$SearchButton.disable();

            require([
                'package/quiqqer/customer/bin/backend/controls/AdministrationWindow'
            ], (Window) => {
                new Window({
                    autoclose: true,
                    multiple: this.getAttribute('multiple'),
                    search: this.getAttribute('search'),
                    searchSettings: this.getAttribute('searchSettings'),
                    events: {
                        onSubmit: (Win, userIds) => {
                            for (let i = 0, len = userIds.length; i < len; i++) {
                                this.addItem(userIds[i]);
                            }
                        }
                    }
                }).open();

                this.$SearchButton.setAttribute('icon', oldIcon);
                this.$SearchButton.enable();
            });
        },

        /**
         * opens the customer creation dialog
         */
        createCustomer: function() {
            const oldIcon = this.$SearchButton.getAttribute('icon');

            this.$SearchButton.setAttribute('icon', 'fa fa-spinner fa-spin');
            this.$SearchButton.disable();

            require([
                'package/quiqqer/customer/bin/backend/controls/create/CustomerWindow'
            ], (CustomerWindow) => {
                new CustomerWindow({
                    events: {
                        submit: (Instance, customerId) => {
                            this.addItem(customerId);
                        }
                    }
                }).open();

                this.$SearchButton.setAttribute('icon', oldIcon);
                this.$SearchButton.enable();
            });
        },

        editCustomer: function() {
            if (this.getValue() === '') {
                return;
            }

            if (!this.getValue()) {
                return;
            }

            const Item = this.getElm().getElement('.qui-elements-selectItem');

            if (Item) {
                Item.dispatchEvent(
                    new MouseEvent('dblclick', {
                        'view': window,
                        'bubbles': true,
                        'cancelable': true
                    })
                );
            }
        }
    });
});
