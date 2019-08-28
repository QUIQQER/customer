/**
 * @module package/quiqqer/customer/bin/backend/controls/CreateCustomerWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/CreateCustomerWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'Locale',
    'package/quiqqer/customer/bin/backend/controls/CreateCustomer'

], function (QUI, QUIConfirm, QUILocale, CreateCustomer) {
    "use strict";

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/customer/bin/backend/controls/CreateCustomerWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight: 600,
            maxWidth : 800
        },

        initialize: function (options) {
            this.setAttributes({
                icon         : 'fa fa-id-card',
                title        : QUILocale.get('quiqqer/customer', 'window.customer.creation.title'),
                cancel_button: {
                    text     : QUILocale.get('quiqqer/system', 'cancel'),
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : QUILocale.get('quiqqer/customer', 'window.customer.creation.button.submit'),
                    textimage: 'icon-ok fa fa-id-card'
                }
            });

            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            this.getContent().set('html', '');

            new CreateCustomer({
                events: {
                    onLoad: function () {

                    }
                }
            }).inject(this.getContent());
        }
    });
});
