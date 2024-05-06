/**
 * @module package/quiqqer/customer/bin/backend/controls/CreateCustomer
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/CreateCustomer', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'Ajax',
    'Locale',

    'css!package/quiqqer/customer/bin/backend/controls/CreateCustomer.css'

], function(QUI, QUIControl, QUIButton, QUIAjax, QUILocale) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/customer/bin/backend/controls/CreateCustomer',

        Binds: [
            '$onInject'
        ],

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$Categories = null;
            this.$Content = null;

            this.$buttons = [];
        },

        /**
         * event: on inject
         */
        $onInject: function() {
            var self = this;

            this.$Categories = new Element('div', {
                'class': 'quiqqer-customer-create-customer-categories',
                html: ''
            }).inject(this.getElm());

            this.$Content = new Element('div', {
                'class': 'quiqqer-customer-create-customer-content',
                html: ''
            }).inject(this.getElm());

            this.getCategories().then(function(categories) {
                var i, len, category, Button;

                var onClick = function(Btn) {
                    var data = Btn.getAttribute('data');

                    self.$buttons.each(function(B) {
                        B.setNormal();
                    });


                    Btn.setActive();

                    require([data.require], function(Control) {
                        self.$Content.set('html', '');
                        new Control().inject(self.$Content);
                    });
                };

                for (i = 0, len = categories.length; i < len; i++) {
                    category = categories[i];

                    Button = new QUIButton({
                        text: category.text,
                        icon: category.icon,
                        textimage: category.textimage,
                        data: category,
                        events: {
                            onClick: onClick
                        }
                    });

                    Button.inject(self.$Categories);

                    self.$buttons.push(Button);
                }
            }).then(function() {
                self.fireEvent('load', [self]);
            });
        },

        /**
         * Return the categories
         *
         * @return {Promise}
         */
        getCategories: function() {
            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_create_getCategories', resolve, {
                    'package': 'quiqqer/customer'
                });
            });
        }
    });
});
