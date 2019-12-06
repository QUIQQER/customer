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

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation.html'

], function (QUI, QUIControl, QUIAjax, QUILocale, Users, Mustache, template) {
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
                title          : QUILocale.get(lg, 'customer.user.information.title'),
                textUSt        : QUILocale.get(lg, 'customer.user.information.USt'),
                textBruttoNetto: QUILocale.get(lg, 'customer.user.information.textBruttoNetto'),
                textTaxInfo    : QUILocale.get(lg, 'customer.user.information.taxInformation'),
                textCompanyNo  : QUILocale.get(lg, 'customer.user.information.textCompanyNo'),

                titleGeneral    : QUILocale.get(lg, 'customer.user.information.general'),
                textLanguage    : QUILocale.get('quiqqer/quiqqer', 'language'),
                textDiscount    : QUILocale.get(lg, 'customer.user.information.discount'),
                textDiscountDesc: QUILocale.get(lg, 'customer.user.information.discount.description'),

                textSendMail      : QUILocale.get(lg, 'customer.user.information.discount.passwordMail'),
                textSendMailButton: QUILocale.get(lg, 'customer.user.information.discount.passwordMail.button')
            }));

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

                Form.elements.lang.value    = User.getAttribute('lang');
                Form.elements.taxInfo.value = tax.vat + '% - ' + tax.area.title;
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
        }
    });
});
