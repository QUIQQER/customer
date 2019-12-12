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
            maxWidth  : 1200,
            maxHeight : 800,
            editable  : false,
            customerId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title    : QUILocale.get(lg, 'window.customer.select.title'),
                icon     : 'fa fa-user-o',
                autoclose: false,
                ok_button: {
                    text     : QUILocale.get(lg, 'window.customer.select.button'),
                    textimage: 'fa fa-user-o'
                }
            });

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this;

            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);

            this.$Admin = new Administration({
                editable   : this.getAttribute('editable'),
                customerId : this.getAttribute('customerId'),
                submittable: true,
                events     : {
                    onCustomerOpenBegin: function () {
                        self.Loader.show();
                    },

                    onCustomerOpen: function () {
                        self.$Title.getElement('[name="close"]').setStyle('display', 'none');

                        new Element('button', {
                            'class': 'fa fa-angle-double-left qui-window-popup-title-close',
                            name   : 'show-list',
                            events : {
                                click: function () {
                                    self.$Title.getElement('[name="show-list"]').destroy();
                                    self.$Title.getElement('[name="close"]').setStyle('display', null);
                                    self.$Admin.closeCustomer();
                                }
                            }
                        }).inject(self.$Title);
                    },

                    onCustomerOpenEnd: function () {
                        self.Loader.hide();
                    },

                    onSubmit: function () {
                        self.submit();
                    }
                }
            });

            this.$Admin.inject(this.getContent());
            this.$Admin.resize();
        },

        /**
         * submit the window
         */
        submit: function () {
            var ids = this.$Admin.getSelectedCustomerIds();

            if (!ids.length) {
                return;
            }

            this.fireEvent('submit', [this, ids]);
            this.close();
        }
    });
});
