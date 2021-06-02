/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/Panel.UserInformation
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles', [

    'qui/QUI',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',

    'Packages',

    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'controls/grid/Grid',
    'Ajax',
    'Locale',
    'Users',

    'css!package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles.css'

], function (QUI, QUILoader, QUIButton, QUIPackages, QUIControl, QUIConfirm, Grid, QUIAjax, QUILocale, Users) {
    "use strict";

    var lg        = 'quiqqer/customer';
    var lgQUIQQER = 'quiqqer/quiqqer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles',

        Binds: [
            '$onInject',
            'openUpload',
            'openDeleteDialog',
            'download',
            '$onClickDownload',
            '$onClickUserDownload'
        ],

        options: {
            userId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$permissions = null;

            this.Loader                  = new QUILoader();
            this.$GridParent             = null;
            this.$userDownloadsInstalled = false;

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

            this.$GridContainer = new Element('div', {
                styles: {
                    height: '100%'
                }
            }).inject(this.$Elm);

            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * Build GRID
         *
         * @return {Promise}
         */
        $buildGrid: function () {
            return new Promise((resolve) => {
                QUIPackages.isInstalled('quiqqer/user-downloads').then((isInstalled) => {
                    this.$userDownloadsInstalled = isInstalled;

                    this.$Grid = new Grid(this.$GridContainer, {
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
                        }, {
                            header   : QUILocale.get(lg, 'window.customer.upload.tbl.header.actions'),
                            dataIndex: 'actions',
                            dataType : 'node',
                            width    : 250
                        }]
                    });

                    this.$Grid.addEvent('click', () => {
                        this.getPermissions().then((permissions) => {
                            if (permissions.fileEdit) {
                                this.$Grid.getButton('delete').enable();
                            }
                        });
                    });

                    this.$Grid.addEvent('dblClick', this.download);

                    resolve();
                });
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.Loader.show();

            this.$buildGrid().then(() => {
                this.resize();
                this.refresh();

                this.Loader.hide();
            });
        },

        /**
         * refresh the list
         */
        refresh: function () {
            this.getPermissions().then((permissions) => {
                if (permissions.fileUpload) {
                    this.$Grid.getButton('upload').enable();
                }

                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_getList', (list) => {
                    for (let i = 0, len = list.length; i < len; i++) {
                        list[i].icon_node = new Element('img', {
                            src   : list[i].icon,
                            styles: {
                                margin: '5px 0'
                            }
                        });

                        const ButtonContainer = new Element('div', {
                            'class': 'quiqqer-customer-userfiles-actions'
                        });

                        list[i].actions = ButtonContainer;

                        new QUIButton({
                            icon  : 'fa fa-download',
                            title : QUILocale.get(lg, 'window.customer.upload.tbl.btn.download'),
                            row   : list[i],
                            events: {
                                onClick: this.$onClickDownload
                            }
                        }).inject(ButtonContainer);

                        if (this.$userDownloadsInstalled) {
                            let btnClass = '';

                            if (list[i].userDownload) {
                                btnClass = 'btn-green';
                            }

                            new QUIButton({
                                'class': btnClass,
                                icon   : 'fa fa-user',
                                title  : QUILocale.get(lg, 'window.customer.upload.tbl.btn.user_download'),
                                row    : list[i],
                                events : {
                                    onClick: this.$onClickUserDownload
                                }
                            }).inject(ButtonContainer);
                        }
                    }

                    this.$Grid.setData({
                        data: list
                    });

                    this.fireEvent('load');
                }, {
                    'package' : 'quiqqer/customer',
                    customerId: this.getAttribute('userId')
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
        },

        /**
         * On "download" button click
         *
         * @param {Object} Btn - QUIButton
         */
        $onClickDownload: function (Btn) {
            this.download(Btn.getAttribute('row'));
        },

        /**
         * On "make user download" button click
         *
         * @param {Object} Btn - QUIButton
         */
        $onClickUserDownload: function (Btn) {
            const Row  = Btn.getAttribute('row');
            const file = Row.filename + '.' + Row.extension;

            if (Row.userDownload) {
                new QUIConfirm({
                    maxHeight: 400,
                    maxWidth : 600,

                    autoclose         : false,
                    backgroundClosable: true,

                    information: QUILocale.get(lg, 'window.customer.userDownload.remove.information', {
                        file: file
                    }),
                    title      : QUILocale.get(lg, 'window.customer.userDownload.remove.title'),
                    texticon   : 'fa fa-user',
                    text       : QUILocale.get(lg, 'window.customer.userDownload.remove.text'),
                    icon       : 'fa fa-user',

                    cancel_button: {
                        text     : false,
                        textimage: 'icon-remove fa fa-remove'
                    },
                    ok_button    : {
                        text     : QUILocale.get(lg, 'window.customer.userDownload.remove.btn.submit'),
                        textimage: 'icon-ok fa fa-check'
                    },
                    events       : {
                        onSubmit: (Win) => {
                            Win.Loader.show();

                            this.$removeFileFromDownloadEntry(file).then(() => {
                                this.refresh();
                                Win.close();
                            }).catch(() => {
                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            } else {
                new QUIConfirm({
                    maxHeight: 400,
                    maxWidth : 600,

                    autoclose         : false,
                    backgroundClosable: true,

                    information: QUILocale.get(lg, 'window.customer.userDownload.add.information', {
                        file: file
                    }),
                    title      : QUILocale.get(lg, 'window.customer.userDownload.add.title'),
                    texticon   : 'fa fa-user',
                    text       : QUILocale.get(lg, 'window.customer.userDownload.add.text'),
                    icon       : 'fa fa-user',

                    cancel_button: {
                        text     : false,
                        textimage: 'icon-remove fa fa-remove'
                    },
                    ok_button    : {
                        text     : QUILocale.get(lg, 'window.customer.userDownload.add.btn.submit'),
                        textimage: 'icon-ok fa fa-check'
                    },
                    events       : {
                        onSubmit: (Win) => {
                            Win.Loader.show();

                            this.$addFileToDownloadEntry(file).then(() => {
                                this.refresh();
                                Win.close();
                            }).catch(() => {
                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            }
        },

        /**
         * Add file to customer DownloadEntry
         *
         * @param {string} file - File name
         * @return {Promise}
         */
        $addFileToDownloadEntry: function (file) {
            return new Promise((resolve) => {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_files_downloadEntry_addFile', resolve, {
                    'package' : 'quiqqer/customer',
                    file      : file,
                    customerId: this.getAttribute('userId')
                });
            });
        },

        /**
         * Remove file from customer DownloadEntry
         *
         * @param {string} file - File name
         * @return {Promise}
         */
        $removeFileFromDownloadEntry: function (file) {
            return new Promise((resolve) => {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_files_downloadEntry_removeFile', resolve, {
                    'package' : 'quiqqer/customer',
                    file      : file,
                    customerId: this.getAttribute('userId')
                });
            });
        },

        /**
         * Directly download userfile
         *
         * @param {Object} [data] - Row data
         */
        download: function (data) {
            if (!data) {
                data = this.$Grid.getSelectedData();

                if (!data.length) {
                    return;
                }

                data = data[0];
            }

            var uid = String.uniqueID();
            var id  = 'download-customer-file-' + uid;

            new Element('iframe', {
                src   : URL_OPT_DIR + 'quiqqer/customer/bin/backend/download.php?' + Object.toQueryString({
                    file      : data.filename,
                    extension : data.extension,
                    customerId: this.getAttribute('userId')
                }),
                id    : id,
                styles: {
                    position: 'absolute',
                    top     : -200,
                    left    : -200,
                    width   : 50,
                    height  : 50
                }
            }).inject(document.body);

            (function () {
                document.getElements('#' + id).destroy();
            }).delay(20000, this);
        }
    });
});
