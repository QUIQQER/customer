/**
 * @module package/quiqqer/customer/bin/backend/controls/AdministrationWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/AdministrationWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/customer/bin/backend/controls/Administration',
    'Locale'

], function (QUI, QUIConfirm, Administration, QUILocale) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/customer/bin/backend/controls/AdministrationWindow',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxWidth : 1200,
            maxHeight: 800
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get(lg, 'window.customer.creation.title')
            });

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

            this.$Admin = new Administration();
            this.$Admin.inject(this.getContent());
            this.$Admin.resize();
        },


        submit: function () {
            var ids = this.$Admin.getSelectedCustomerIds();

            this.fireEvent('submit', [this, ids]);

            if (this.getAttribute('autoclose')) {
                this.close();
            }
        }
    });
});
