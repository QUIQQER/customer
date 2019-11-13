/**
 * @module package/quiqqer/customer/bin/backend/controls/create/CustomerWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/create/CustomerWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'Locale',
    'package/quiqqer/customer/bin/backend/controls/create/Customer'

], function (QUI, QUIPopup, QUILocale, CreateCustomer) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/customer/bin/backend/controls/create/CustomerWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight: 600,
            maxWidth : 800,
            buttons  : false
        },

        initialize: function (options) {
            this.setAttributes({
                icon : 'fa fa-id-card',
                title: QUILocale.get(lg, 'customer.window.create.title')
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
            this.getContent().setStyle('padding', 0);

            new CreateCustomer({
                events: {
                    onLoad: function () {

                    }
                }
            }).inject(this.getContent());
        }
    });
});
