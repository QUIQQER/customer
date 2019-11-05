/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties.html'

], function (QUI, QUIControl, QUIAjax, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties',

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
                title        : QUILocale.get(lg, 'customer.user.properties.title'),
                textStatus   : QUILocale.get(lg, 'customer.user.properties.status'),
                titlePassword: QUILocale.get(lg, 'customer.user.properties.password.title'),
                textPassword1: QUILocale.get(lg, 'customer.user.properties.password.title'),
                textPassword2: QUILocale.get(lg, 'customer.user.properties.password.title')
            }));

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getCustomerLoginFlag', function (login) {
                var Form = self.$Elm.getElement('form');

                if (!login) {
                    new Element('div', {
                        'class': 'messages-message message-attention',
                        html   : QUILocale.get(lg, 'message.customer.cant.log.in'),
                        styles : {
                            marginBottom: 20
                        }
                    }).inject(Form, 'top');

                    self.fireEvent('load', [self]);
                    return;
                }

                Form.elements.status.set('disabled', false);
                Form.elements.password1.set('disabled', false);
                Form.elements.password2.set('disabled', false);

                self.fireEvent('load', [self]);
            }, {
                'package': 'quiqqer/customer'
            });
        }
    });
});
