/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/grid/Grid',
    'Ajax',
    'Locale',
    'Users'

], function (QUI, QUIControl, Grid, QUIAjax, QUILocale, Users) {
    "use strict";

    var lg        = 'quiqqer/customer';
    var lgQUIQQER = 'quiqqer/quiqqer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles',

        Binds: [
            '$onInject',
            'openUpload'
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
         *
         * @return {Promise|Promise|Promise|Promise}
         */
        resize: function () {
            return Promise.all([
                this.$Grid.setWidth(this.$Elm.getSize().x),
                this.$Grid.setHeight(this.$Elm.getSize().y)
            ]);
        },

        /**
         * Return the DOMNode Element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();
            this.$Elm.set('html', '');
            this.$Elm.set('data-quiid', this.getId());

            this.$Elm.setStyles({
                height: '100%'
            });

            var Container = new Element('div', {
                styles: {
                    height: '100%'
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                buttons    : [{
                    name     : 'upload',
                    text     : QUILocale.get(lg, 'customer.files.upload.button'),
                    textimage: 'fa fa-upload',
                    events   : {
                        onClick: this.openUpload
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get(lgQUIQQER, 'file'),
                    dataIndex: 'basename',
                    dataType : 'string',
                    width    : 300
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'size'),
                    dataIndex: 'filesize_formatted',
                    dataType : 'string',
                    width    : 100
                }]
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.resize();

            QUIAjax.get('package_quiqqer_customer_ajax_backend_files_getList', function (list) {
                console.log(list);

                self.$Grid.setData({
                    data: list
                });

                self.fireEvent('load');
            }, {
                'package' : 'quiqqer/customer',
                customerId: self.getAttribute('userId')
            });
        },

        /**
         * @todo
         */
        openUpload: function () {

        }
    });
});
