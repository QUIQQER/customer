/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'controls/grid/Grid',
    'Ajax',
    'Locale',
    'Users'

], function (QUI, QUIControl, QUIConfirm, Grid, QUIAjax, QUILocale, Users) {
    "use strict";

    var lg        = 'quiqqer/customer';
    var lgQUIQQER = 'quiqqer/quiqqer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles',

        Binds: [
            '$onInject',
            'openUpload',
            'openDeleteDialog'
        ],

        options: {
            userId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$permissions = null;

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
            var self = this;

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
                    disabled : true,
                    textimage: 'fa fa-upload',
                    events   : {
                        onClick: this.openUpload
                    }
                }, {
                    type: 'separator'
                }, {
                    name     : 'delete',
                    text     : QUILocale.get(lgQUIQQER, 'delete'),
                    disabled : true,
                    textimage: 'fa fa-trash',
                    events   : {
                        onClick: this.openDeleteDialog
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get(lgQUIQQER, 'type'),
                    dataIndex: 'icon_node',
                    dataType : 'node',
                    width    : 40
                }, {
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

            this.$Grid.addEvent('click', function () {
                self.getPermissions().then(function (permissions) {
                    if (permissions.fileEdit) {
                        self.$Grid.getButton('delete').enable();
                    }
                });
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.resize();
            this.refresh();
        },

        /**
         * refresh the list
         */
        refresh: function () {
            var self = this;

            this.getPermissions().then(function (permissions) {
                if (permissions.fileUpload) {
                    self.$Grid.getButton('upload').enable();
                }

                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_getList', function (list) {
                    for (var i = 0, len = list.length; i < len; i++) {
                        list[i].icon_node = new Element('img', {
                            src   : list[i].icon,
                            styles: {
                                margin: '5px 0'
                            }
                        });
                    }

                    self.$Grid.setData({
                        data: list
                    });

                    self.fireEvent('load');
                }, {
                    'package' : 'quiqqer/customer',
                    customerId: self.getAttribute('userId')
                });
            });
        },

        /**
         * Return the file permissions
         *
         * @return {Promise}
         */
        getPermissions: function () {
            var self = this;

            if (self.$permissions !== null) {
                return Promise.resolve(self.$permissions);
            }

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_getPermissions', function (permissions) {
                    self.$permissions = permissions;
                    resolve(self.$permissions);
                }, {
                    package: 'quiqqer/customer'
                });
            });
        },

        /**
         * Open Upload Window
         */
        openUpload: function () {
            var self = this;

            new QUIConfirm({
                title    : QUILocale.get(lg, 'window.customer.upload.title'),
                icon     : 'fa fa-upload',
                maxWidth : 600,
                maxHeight: 400,
                autoclose: false,
                events   : {
                    onOpen: function (Win) {
                        Win.getContent().set('html', '');
                        Win.Loader.show();

                        require(['controls/upload/Form'], function (Form) {
                            self.$Form = new Form({
                                pauseAllowed: false,
                                contextMenu : false,
                                events      : {
                                    onBegin: function () {
                                        Win.Loader.show();
                                    },

                                    onComplete: function () {
                                        self.refresh();
                                        Win.close();
                                    },

                                    onDragenter: function (event) {
                                        event.stop();
                                    }
                                }
                            }).inject(Win.getContent());

                            self.$Form.setParam('onfinish', 'package_quiqqer_customer_ajax_backend_files_upload');
                            self.$Form.setParam('package', 'quiqqer/customer');
                            self.$Form.setParam('customerId', self.getAttribute('userId'));

                            Win.Loader.hide();
                        });
                    },

                    onSubmit: function () {
                        if (self.$Form.getFiles().length) {
                            self.$Form.submit();
                        }
                    }
                }
            }).open();
        },

        /**
         * Open Delete Window
         */
        openDeleteDialog: function () {
            var self     = this,
                selected = this.$Grid.getSelectedData();

            new QUIConfirm({
                icon       : 'fa fa-trash',
                texticon   : 'fa fa-trash',
                title      : QUILocale.get(lg, 'window.customer.delete.title'),
                information: QUILocale.get(lg, 'window.customer.delete.information'),
                text       : QUILocale.get(lg, 'window.customer.delete.text'),
                maxWidth   : 600,
                maxHeight  : 400,
                autoclose  : false,
                events     : {
                    onOpen  : function (Win) {
                        var List = new Element('ul');

                        for (var i = 0, len = selected.length; i < len; i++) {
                            new Element('li', {
                                html: selected[i].basename
                            }).inject(List);
                        }

                        List.inject(
                            Win.getContent().getElement('.information'),
                            'after'
                        );
                    },
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        var files = selected.map(function (e) {
                            return e.basename;
                        });

                        QUIAjax.post('package_quiqqer_customer_ajax_backend_files_delete', function () {
                            Win.close();
                            self.refresh();
                        }, {
                            package   : 'quiqqer/customer',
                            files     : JSON.encode(files),
                            customerId: self.getAttribute('userId')
                        });
                    }
                }
            }).open();
        }
    });
});
