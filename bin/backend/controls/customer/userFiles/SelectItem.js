/**
 * Select item for customer file select.
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem', [

    'qui/controls/elements/SelectItem',
    'qui/controls/windows/Confirm',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem.Entry.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem.css'

], function (QUISelectItem, QUIConfirm, QUILocale, QUIAjax, Mustache, templateEntry) {
    "use strict";

    const lg = 'quiqqer/customer';

    return new Class({
        Extends: QUISelectItem,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem',

        Binds: [
            'refresh',
            '$getFileData',
            '$onClickDownload',
            '$onClickAttachmentSwitch',
            'getItemOptions',
            '$onClickDelete'
        ],

        options: {
            confirmDelete: false // deletion requires confirmation
        },

        initialize: function (options) {
            this.parent(options);
            this.setAttribute('icon', 'fa fa-file-text-o');

            this.$Download         = null;
            this.$File             = null;
            this.$AttachmentSwitch = null;
            this.$ParentSelect     = this.getAttribute('Parent');
        },

        /**
         * event : on inject
         */
        refresh: function () {
            this.$Text.set({
                html: '<span class="fa fa-spinner fa-spin"></span>'
            });

            this.$getFileData(this.getAttribute('id')).then((File) => {
                this.$File = File;

                this.$Text.set({
                    html: Mustache.render(templateEntry, {
                        title   : File.basename,
                        fileHash: File.hash
                    })
                });

                this.$AttachmentSwitch = new Element('span', {
                    'class'      : 'qui-elements-selectItem-attachment fa fa-envelope',
                    title        : QUILocale.get(lg, 'controls.userFiles.SelectItem.btn.attachment.title'),
                    'data-active': 0,
                    events       : {
                        click: this.$onClickAttachmentSwitch
                    }
                }).inject(this.$Destroy, 'before');

                this.$Download = new Element('span', {
                    'class': 'qui-elements-selectItem-download fa fa-download',
                    title  : QUILocale.get(lg, 'controls.userFiles.SelectItem.btn.download.title'),
                    events : {
                        click: this.$onClickDownload
                    }
                }).inject(this.$Destroy, 'before');

                if (this.getAttribute('confirmDelete')) {
                    this.$Destroy.removeEvents('click');
                    this.$Destroy.addEvent('click', this.$onClickDelete);
                }

                //this.$ParentSelect.fireEvent('change', [this.$ParentSelect]);

                this.$Text.addEvent('dblclick', this.$onClickDownload);

                this.fireEvent('itemAddCompleted', [File, this]);
            }).catch((e) => {
                this.$Icon.removeClass('fa fa-file-text-o');
                this.$Icon.addClass('fa-bolt');
                this.$Text.set('html', '...');
            });
        },

        /**
         * Set options to a specific item
         *
         * @param {Object} Options
         */
        setItemOptions: function (Options) {
            if ('attachToEmail' in Options && !!Options.attachToEmail) {
                this.$onClickAttachmentSwitch();
            }
        },

        /**
         * Get file info
         *
         * @param {String} fileHash
         * @return {Promise}
         */
        $getFileData: function (fileHash) {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_get', resolve, {
                    'package' : 'quiqqer/customer',
                    customerId: this.getAttribute('Parent').getAttribute('userId'),
                    fileHash  : fileHash,
                    onError   : reject
                });
            });
        },

        /**
         * Direct download customer file
         *
         * @return {void}
         */
        $onClickDownload: function () {
            const uid = String.uniqueID();
            const id  = 'download-customer-file-' + uid;

            new Element('iframe', {
                src   : URL_OPT_DIR + 'quiqqer/customer/bin/backend/download.php?' + Object.toQueryString({
                    file      : this.$File.filename,
                    extension : this.$File.extension,
                    customerId: this.getAttribute('Parent').getAttribute('userId')
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
        },

        /**
         * Toggle "is email attachment" button
         */
        $onClickAttachmentSwitch: function () {
            if (parseInt(this.$AttachmentSwitch.get('data-active'))) {
                this.$AttachmentSwitch.setStyles({
                    background: 'initial',
                    color     : 'initial'
                });

                this.$AttachmentSwitch.set('data-active', 0);
            } else {
                this.$AttachmentSwitch.setStyles({
                    background: '#2F8FC6',
                    color     : '#FFFFFF'
                });

                this.$AttachmentSwitch.set('data-active', 1);
            }

            this.$ParentSelect.fireEvent('change', [this.$ParentSelect]);
        },

        /**
         * Get options
         *
         * @return {Object}
         */
        getItemOptions: function () {
            return {
                attachToEmail: parseInt(this.$AttachmentSwitch.get('data-active')) ? true : false
            };
        },

        /**
         * Item delete confirmation
         */
        $onClickDelete: function () {
            new QUIConfirm({
                maxHeight: 350,
                maxWidth : 650,

                autoclose         : false,
                backgroundClosable: true,

                information: QUILocale.get(lg, 'controls.userFiles.SelectItem.delete.information', {
                    file: this.$File.basename
                }),
                title      : QUILocale.get(lg, 'controls.userFiles.SelectItem.delete.title'),
                texticon   : 'fa fa-trash',
                text       : QUILocale.get(lg, 'controls.userFiles.SelectItem.delete.text'),
                icon       : 'fa fa-trash',

                ok_button: {
                    text     : QUILocale.get(lg, 'controls.userFiles.SelectItem.delete.submit'),
                    textimage: 'icon-ok fa fa-trash'
                },
                events   : {
                    onSubmit: (Win) => {
                        this.destroy();
                        //this.$ParentSelect.fireEvent('change', [this.$ParentSelect]);

                        Win.close();
                    }
                }
            }).open();
        }
    });
});
