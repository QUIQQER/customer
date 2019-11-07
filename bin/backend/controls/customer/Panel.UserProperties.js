/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/Panel.UserProperties.html'

], function (QUI, QUIControl, QUIConfirm, QUIAjax, QUILocale, Mustache, template) {
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
                title                : QUILocale.get(lg, 'customer.user.properties.title'),
                textStatus           : QUILocale.get(lg, 'customer.user.properties.status'),
                textStatusDescription: QUILocale.get(lg, 'customer.user.properties.status.description'),

                titlePassword: QUILocale.get(lg, 'customer.user.properties.password.title'),
                textPassword1: QUILocale.get(lg, 'customer.user.properties.password.1'),
                textPassword2: QUILocale.get(lg, 'customer.user.properties.password.2'),

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

                Form.elements.password1.addEvent('blur', function () {
                    if (Form.elements.password1.value !== Form.elements.password2.value) {
                        Form.elements.password2.focus();
                    }
                });

                Form.elements.password2.addEvent('blur', function () {
                    if (Form.elements.password1.value !== Form.elements.password2.value) {
                        QUI.getMessageHandler().then(function (MH) {
                            MH.addError(
                                QUILocale.get(lg, 'message.passwords.incorrect'),
                                Form.elements.password2
                            );
                        });
                    }
                });

                Form.elements.passwordMail.addEvent('click', function (event) {
                    event.stop();
                    self.passwordResetMail();
                });

                self.fireEvent('load', [self]);
            }, {
                'package': 'quiqqer/customer'
            });
        },

        /**
         * password reset confirmation window
         * -> send a password reset mail to the user
         */
        passwordResetMail: function () {
            var self = this;

            new QUIConfirm({
                icon       : 'fa fa-envelope',
                texticon   : 'fa fa-envelope',
                title      : QUILocale.get(lg, 'password.mail.window.title'),
                text       : QUILocale.get(lg, 'password.mail.window.text'),
                information: QUILocale.get(lg, 'password.mail.window.information'),
                maxHeight  : 300,
                maxWidth   : 600,
                autoclose  : false,
                ok_button  : {
                    text     : QUILocale.get(lg, 'password.mail.window.submit'),
                    textimage: 'fa fa-envelope'
                },
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        QUIAjax.post('package_quiqqer_customer_ajax_backend_customer_passwordMail', function () {
                            Win.close();
                        }, {
                            'package': 'quiqqer/customer',
                            userId   : self.getAttribute('userId')
                        });
                    }
                }
            }).open();
        }
    });
});
