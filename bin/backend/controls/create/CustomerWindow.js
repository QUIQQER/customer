/**
 * @module package/quiqqer/customer/bin/backend/controls/create/CustomerWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/create/CustomerWindow', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'Locale',
    'package/quiqqer/customer/bin/backend/controls/create/Customer'

], function(QUI, QUIPopup, QUILocale, CreateCustomer) {
    'use strict';

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIPopup,
        Type: 'package/quiqqer/customer/bin/backend/controls/create/CustomerWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight: 750,
            maxWidth: 600,
            buttons: false
        },

        initialize: function(options) {
            this.setAttributes({
                icon: 'fa fa-id-card',
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
        $onOpen: function() {
            var self = this;

            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);
            this.Loader.show();

            new CreateCustomer({
                events: {
                    onLoad: function() {
                        self.Loader.hide();
                    },

                    onCreateCustomerBegin: function() {
                        self.Loader.show();
                    },

                    onCreateCustomerEnd: function(Instance, customerId) {
                        self.fireEvent('submit', [self, customerId]);
                        self.close();
                    }
                }
            }).inject(this.getContent());
        }
    });
});
