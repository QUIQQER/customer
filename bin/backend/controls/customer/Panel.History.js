/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.History', [

    'package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments',
    'Ajax',
    'Locale'

], function (ParentControl, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: ParentControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.History',

        Binds: [
            '$onInject'
        ],

        options: {
            userId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$editComments = false;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Event: onCreate
         */
        $onInject: function () {
            this.parent();
            this.$AddCommentButton.destroy();
        },

        // region comment list

        /**
         * return all history entries for the user
         *
         * @return {Promise}
         */
        getComments: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getHistory', resolve, {
                    'package': 'quiqqer/customer',
                    uid      : self.getAttribute('userId')
                });
            });
        }
    });
});
