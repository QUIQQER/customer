/**
 * Provides the optional payment method select for the customer settings.
 *
 * @module package/quiqqer/customer/bin/backend/controls/settings/CustomerPaymentSelect
 */
define('package/quiqqer/customer/bin/backend/controls/settings/CustomerPaymentSelect', [
    'qui/controls/elements/Select',
    'Packages',
    'Locale'
], function (QUIElementSelect, Packages, QUILocale) {
    'use strict';

    const lg = 'quiqqer/customer';
    const paymentSelect = 'package/quiqqer/payments/bin/backend/controls/Select';

    return new Class({
        Extends: QUIElementSelect,
        Type: 'package/quiqqer/customer/bin/backend/controls/settings/CustomerPaymentSelect',

        Binds: [
            '$onImport',
            '$renderDisabled',
            '$renderPaymentSelect'
        ],

        initialize: function (options, Input) {
            this.parent(options, Input);

            this.setAttribute('icon', 'fa fa-credit-card-alt');
        },

        $onImport: function () {
            const Elm = this.getElm();

            if (Elm && Elm.nodeName === 'INPUT') {
                this.$Input = Elm;
                this.$Input.disabled = true;
            }

            Packages.isInstalled('quiqqer/payments').then((isInstalled) => {
                if (!isInstalled) {
                    this.$renderDisabled();
                    return;
                }

                this.$renderPaymentSelect();
            }).catch(this.$renderDisabled);
        },

        $renderPaymentSelect: function () {
            require([paymentSelect], (PaymentSelect) => {
                this.setAttribute(
                    'child',
                    'package/quiqqer/payments/bin/backend/controls/SelectItem'
                );
                this.setAttribute('Search', PaymentSelect.prototype.paymentSearch.bind(this));
                this.addEvent(
                    'onSearchButtonClick',
                    PaymentSelect.prototype.$onSearchButtonClick.bind(this)
                );

                if (this.$Input) {
                    this.$Input.disabled = false;
                }

                QUIElementSelect.prototype.$onImport.call(this);
            }, this.$renderDisabled);
        },

        $renderDisabled: function () {
            this.setAttribute(
                'placeholder',
                QUILocale.get(lg, 'customer.settings.defaultPaymentMethod.paymentsMissing')
            );

            QUIElementSelect.prototype.$onImport.call(this);
            this.disable();
        }
    });
});
