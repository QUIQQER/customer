/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/erp/bin/backend/controls/Comments',
    'Ajax'

], function (QUI, QUIControl, Comments, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments',

        Binds: [
            '$onInject'
        ],

        options: {
            userId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Comments = null;

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

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.$Comments = new Comments();
            this.$Comments.inject(this.$Elm);

            this.getComments().then(function (comments) {
                comments = comments.reverse();

                self.$Comments.unserialize(comments);
                self.fireEvent('load');
            });
        },

        /**
         * return all comments for the user
         *
         * @return {Promise}
         */
        getComments: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_customer_getComments', resolve, {
                    'package': 'quiqqer/customer',
                    uid      : self.getAttribute('userId')
                });
            });
        }
    });
});
