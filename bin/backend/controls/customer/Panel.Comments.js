/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'package/quiqqer/erp/bin/backend/controls/Comments',
    'Ajax'

], function (QUI, QUIControl, QUIButton, QUIConfirm, Comments, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.Comments',

        Binds: [
            'addComment',
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

            new QUIButton({
                textimage: 'fa fa-comment',
                text     : 'Kommenatar hinzufügen',
                styles   : {
                    'float': 'right'
                },
                events   : {
                    onClick: this.addComment
                }
            }).inject(this.$Elm);

            new Element('section').inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.refresh().then(function () {
                this.fireEvent('load');
            }.bind(this));
        },

        /**
         * refresh the comments
         *
         * @return {Promise}
         */
        refresh: function (comments) {
            var self    = this;
            var Section = this.$Elm.getElement('section');

            Section.set('html', '');

            this.$Comments = new Comments(comments);
            this.$Comments.inject(Section);

            if (typeof comments !== 'undefined') {
                comments = comments.reverse();
                self.$Comments.unserialize(comments);

                return Promise.resolve();
            }

            return this.getComments().then(function (comments) {
                comments = comments.reverse();
                self.$Comments.unserialize(comments);
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
        },

        /**
         * add a comment
         */
        addComment: function () {
            var self = this;

            new QUIConfirm({
                icon     : 'fa fa-comment',
                title    : 'Kommentar hinzufügen',
                maxHeight: 400,
                maxWidth : 700,
                autoclose: false,
                events   : {
                    onOpen: function (Win) {
                        Win.getContent().set('html', '');

                        var Textarea = new Element('textarea', {
                            styles: {
                                height: '98%',
                                width : '100%'
                            }
                        }).inject(Win.getContent());

                        Textarea.focus();
                    },

                    onSubmit: function (Win) {
                        Win.Loader.show();

                        var comment = Win.getContent().getElement('textarea').value;

                        QUIAjax.post('package_quiqqer_customer_ajax_backend_customer_addComment', function (comments) {
                            Win.close();
                            self.refresh(comments);
                        }, {
                            'package': 'quiqqer/customer',
                            userId   : self.getAttribute('userId'),
                            comment  : comment
                        });
                    }
                }
            }).open();
        }
    });
});
