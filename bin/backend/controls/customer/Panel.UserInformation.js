/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Locale',
    'Users',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation.html',
    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.PriceCalcWindow.html'

], function (
    QUI, QUIControl, QUIAjax, QUILocale, Users, Mustache,
    template, templatePriceCalcWindow
) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation',

        Binds: [
            '$onInject'
        ],

        options: {
            userId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Return the DOMNode Element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.set('html', Mustache.render(template, {
                title             : QUILocale.get(lg, 'customer.user.information.title'),
                textUSt           : QUILocale.get(lg, 'customer.user.information.USt'),
                textBruttoNetto   : QUILocale.get(lg, 'customer.user.information.textBruttoNetto'),
                textTaxInfo       : QUILocale.get(lg, 'customer.user.information.taxInformation'),
                textCompanyNo     : QUILocale.get(lg, 'customer.user.information.textCompanyNo'),
                titleGeneral      : QUILocale.get(lg, 'customer.user.information.general'),
                textLanguage      : QUILocale.get('quiqqer/quiqqer', 'language'),
                textDiscount      : QUILocale.get(lg, 'customer.user.information.discount'),
                textDiscountDesc  : QUILocale.get(lg, 'customer.user.information.discount.description'),
                textSendMail      : QUILocale.get(lg, 'customer.user.information.discount.passwordMail'),
                textSendMailButton: QUILocale.get(lg, 'customer.user.information.discount.passwordMail.button'),
                textNetto         : QUILocale.get('quiqqer/erp', 'user.settings.userNettoStatus.brutto'),
                textBrutto        : QUILocale.get('quiqqer/erp', 'user.settings.userNettoStatus.netto'),

                textCheckCalculationBasis: QUILocale.get(lg, 'customer.user.information.checkCalculation')
            }));

            var StatusCheck = this.$Elm.getElement('[name="quiqqer-erp-determine-calc-status"]');
            var Status      = this.$Elm.getElement('[name="quiqqer.erp.isNettoUser"]');

            if (Status.value !== '') {
                StatusCheck.set('html', '<span class="fa fa-check"></span>');
            }

            StatusCheck.set('disabled', false);
            StatusCheck.addEvent('click', function (e) {
                e.stop();
                this.openCheck();
            }.bind(this));

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;
            var User = Users.get(this.getAttribute('userId'));
            var Form = this.$Elm.getElement('form');

            var checkVal = function (str) {
                if (!str || str === 'false') {
                    str = '';
                }

                return str;
            };

            Form.elements['quiqqer.erp.isNettoUser'].value = User.getAttribute('quiqqer.erp.isNettoUser');
            Form.elements['quiqqer.erp.euVatId'].value     = checkVal(User.getAttribute('quiqqer.erp.euVatId'));
            Form.elements['quiqqer.erp.discount'].value    = checkVal(User.getAttribute('quiqqer.erp.discount'));

            Form.elements['quiqqer.erp.company.identification.number'].value = checkVal(User.getAttribute('quiqqer.erp.company.identification.number'));

            Promise.all([
                this.getTaxByUser(),
                this.getLanguages()
            ]).then(function (result) {
                var tax       = result[0];
                var languages = result[1];

                var LangElm = Form.elements.lang;

                for (var i = 0, len = languages.length; i < len; i++) {
                    new Element('option', {
                        html : QUILocale.get('quiqqer/quiqqer', 'language.' + languages[i]),
                        value: languages[i]
                    }).inject(LangElm);
                }

                Form.elements.lang.value = User.getAttribute('lang');

                if (tax) {
                    Form.elements.taxInfo.value = tax.vat + '% - ' + tax.area.title;
                } else {
                    Form.elements.taxInfo.value = '---';
                }

            }).then(function () {
                return QUI.parse(self.getElm());
            }).then(function () {
                self.fireEvent('load', [self]);
            });
        },

        /**
         * return the tax from the user
         *
         * @return {Promise}
         */
        getTaxByUser: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getTaxByUser', resolve, {
                    'package': 'quiqqer/customer',
                    userId   : self.getAttribute('userId')
                });
            });
        },

        /**
         * return the available languages
         *
         * @return {Promise}
         */
        getLanguages: function () {
            return new Promise(function (resolve) {
                require(['package/quiqqer/translator/bin/Translator'], function (Translator) {
                    Translator.getAvailableLanguages().then(resolve);
                });
            });
        },

        /**
         * open the price calculation window
         */
        openCheck: function () {
            var self = this;

            require(['qui/controls/windows/Confirm'], function (Confirm) {
                new Confirm({
                    icon     : 'fa fa-user',
                    title    : QUILocale.get(lg, 'customer.user.window.checkCalculation'),
                    maxHeight: 600,
                    maxWidth : 800,
                    ok_button: {
                        text     : QUILocale.get(lg, 'customer.user.window.button'),
                        textimage: 'fa fa-check'
                    },
                    events   : {
                        onOpen: function (Win) {
                            var Content = Win.getContent();

                            Content.set('html', Mustache.render(templatePriceCalcWindow, {
                                informationText    : QUILocale.get(lg, 'customer.user.window.text'),
                                textBruttoNetto    : QUILocale.get(lg, 'customer.user.information.textBruttoNetto'),
                                textIsCompany      : QUILocale.get(lg, 'customer.user.window.isCompany'),
                                textCompany        : QUILocale.get(lg, 'customer.user.window.company'),
                                textDefaultAddress : QUILocale.get(lg, 'customer.user.window.defaultAddress'),
                                textShippingAddress: QUILocale.get(lg, 'customer.user.window.shippingAddress'),
                                textNetto          : QUILocale.get('quiqqer/erp', 'user.settings.userNettoStatus.brutto'),
                                textBrutto         : QUILocale.get('quiqqer/erp', 'user.settings.userNettoStatus.netto'),

                                textEuVatId: QUILocale.get('quiqqer/erp', 'user.profile.userdata.vatId'),
                                textTaxId  : QUILocale.get('quiqqer/erp', 'user.settings.taxId'),
                                textChId   : QUILocale.get('quiqqer/erp', 'quiqqer.erp.chUID')
                            }));

                            Win.Loader.show();

                            QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_checkCalculation', function (result) {
                                Content.getElement('[name="nettoStatus"]').set('value', result.status);

                                Content.getElement('[name="euVatId"]')
                                       .set('value', result.euVatId)
                                       .set('disabled', true);

                                if (result.taxId) {
                                    Content.getElement('[name="taxId"]')
                                           .set('value', result.taxId)
                                           .set('disabled', true);
                                } else {
                                    Content.getElement('[name="taxId"]').getParent('tr').setStyle('display', 'none')
                                }

                                if (result.chUID) {
                                    Content.getElement('[name="chId"]')
                                           .set('value', result.chUID)
                                           .set('disabled', true);
                                } else {
                                    Content.getElement('[name="chId"]').getParent('tr').setStyle('display', 'none')
                                }

                                Content.getElement('.window-price-calc-text').setStyle('margin-bottom', 20);

                                if (result.isCompany) {
                                    Content.getElement('.window-price-calc-isCompany').set('html', '<span class="fa fa-check"></span>');
                                    Content.getElement('.window-price-calc-company').set('html', result.address.company);
                                } else {
                                    Content.getElement('.window-price-calc-isCompany').set('html', '<span class="fa fa-close"></span>');
                                    Content.getElement('.window-price-calc-company').set('html', '<span class="fa fa-close"></span>');
                                }

                                if (result.address.text) {
                                    Content.getElement('.window-price-calc-address').set('html', result.address.text);
                                } else {
                                    Content.getElement('.window-price-calc-address').set('html', '---');
                                }

                                if (result.shipping.text) {
                                    Content.getElement('.window-price-shipping-address').set('html', result.shipping.text);
                                } else {
                                    Content.getElement('.window-price-shipping-address').set('html', '---');
                                }

                                Win.Loader.hide();
                            }, {
                                'package': 'quiqqer/customer',
                                userId   : self.getAttribute('userId')
                            });
                        },

                        onSubmit: function (Win) {
                            var Form    = self.$Elm.getElement('form'),
                                Content = Win.getContent();

                            Form.elements['quiqqer.erp.isNettoUser'].value = Content.getElement('[name="nettoStatus"]').value;
                        }
                    }
                }).open();
            });
        }
    });
});
