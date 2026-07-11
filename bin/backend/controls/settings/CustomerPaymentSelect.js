/**
 * Provides the optional payment method select for the customer settings.
 *
 * @module package/quiqqer/customer/bin/backend/controls/settings/CustomerPaymentSelect
 */
define('package/quiqqer/customer/bin/backend/controls/settings/CustomerPaymentSelect', [
    'qui/QUI',
    'qui/controls/Control',
    'Packages',
    'Locale'
], function (QUI, QUIControl, Packages, QUILocale) {
    'use strict';

    const lg = 'quiqqer/customer';

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/customer/bin/backend/controls/settings/CustomerPaymentSelect',

        Binds: [
            '$disableInput',
            '$onImport'
        ],

        initialize: function (options, Input) {
            this.parent(options);

            this.$Input = Input;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function () {
            Packages.isInstalled('quiqqer/payments').then((isInstalled) => {
                if (!isInstalled) {
                    this.$disableInput();
                    return;
                }

                this.$Input.set(
                    'data-qui',
                    'package/quiqqer/payments/bin/backend/controls/Select'
                );
                this.$Input.removeProperty('data-quiid');

                QUI.parse(this.$Input.getParent());
            }).catch(this.$disableInput);
        },

        $disableInput: function () {
            this.$Input.disabled = true;
            this.$Input.value = QUILocale.get(
                lg,
                'customer.settings.defaultPaymentMethod.paymentsMissing'
            );
        }
    });
});
