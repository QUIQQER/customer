/**
 * @module package/quiqqer/customer/bin/backend/controls/create/Customer
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/create/Customer', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/create/Customer.html',

    'css!package/quiqqer/customer/bin/backend/controls/create/Customer.css'

], function (QUI, QUIControl, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/create/Customer',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * event: on create
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-customer-create');
            this.$Elm.set('data-qui', 'package/quiqqer/customer/bin/backend/controls/create/Customer');
            console.log(template);
            this.$Elm.set('html', Mustache.render(template, {
                customerNoHeader     : QUILocale.get(lg, 'window.customer.creation.customerNo.title'),
                customerNoText       : QUILocale.get(lg, 'window.customer.creation.customerNo.text'),
                customerNoInputHeader: QUILocale.get(lg, 'window.customer.creation.customerNo.inputLabel'),
                customerDataHeader   : QUILocale.get(lg, 'window.customer.creation.dataHeader.title'),
                customerDataText     : QUILocale.get(lg, 'window.customer.creation.dataHeader.text'),
                customerGroupsHeader : QUILocale.get(lg, 'window.customer.creation.groups.title'),
                customerGroupsText   : QUILocale.get(lg, 'window.customer.creation.groups.text')
            }));

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {

        },

        /**
         *
         */
        createCustomerNo: function () {

        }
    });
});
