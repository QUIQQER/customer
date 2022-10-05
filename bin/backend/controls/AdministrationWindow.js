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

    const lg = 'quiqqer/customer';

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
            const self = this;

            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);

            const BtnSubmit = this.getButton('submit');

            if (BtnSubmit) {
                BtnSubmit.disable();
            }

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
                            'class': 'qui-window-popup-title-close quiqqer-customer-adminWindow-close',
                            name   : 'show-list',
                            html   : '<span class="fa fa-angle-double-left"></span> ' +
                                     '<span>' +
                                     '' + QUILocale.get(lg, 'customer.window.backToList') +
                                     '</span>',
                            events : {
                                click: function () {
                                    self.$Title.getElement('[name="show-list"]').destroy();
                                    self.$Title.getElement('[name="close"]').setStyle('display', null);
                                    self.$Admin.closeCustomer();

                                    const ExtraButton = self.$Admin.$CustomerPanel.getButtons('extra');

                                    if (ExtraButton) {
                                        ExtraButton.getMenu().then(function (Menu) {
                                            Menu.hide();
                                        });
                                    }
                                }
                            }
                        }).inject(self.$Title);
                    },

                    onCustomerSelect: function () {
                        if (BtnSubmit) {
                            BtnSubmit.enable();
                        }
                    },

                    onCustomerOpenEnd: function () {
                        if (BtnSubmit) {
                            BtnSubmit.enable();
                        }

                        self.Loader.hide();
                    },

                    onSubmit: function () {
                        self.submit();
                    },

                    onRefreshBegin: function () {
                        self.Loader.show();
                    },

                    onRefreshEnd: function () {
                        if (BtnSubmit) {
                            BtnSubmit.disable();
                        }
                        
                        self.Loader.hide();
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
            let ids = this.$Admin.getSelectedCustomerIds();

            if (!ids.length) {
                return;
            }

            if (this.$Admin.$CustomerPanel) {
                this.$Admin.$CustomerPanel.update().then(() => {
                    this.fireEvent('submit', [
                        this,
                        ids
                    ]);
                    this.close();
                });

                return;
            }

            this.fireEvent('submit', [
                this,
                ids
            ]);
            this.close();
        }
    });
});
