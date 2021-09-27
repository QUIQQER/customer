/**
 * Select item for customer file select.
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem', [

    'qui/controls/elements/SelectItem',
    'package/quiqqer/discount/bin/classes/Handler',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem.Entry.html',
    'css!package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem.css'

], function (QUISelectItem, Handler, QUILocale, QUIAjax, Mustache, templateEntry) {
    "use strict";

    const lg = 'quiqqer/customer';

    return new Class({
        Extends: QUISelectItem,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem',

        Binds: [
            'refresh',
            '$getFileData',
            '$onClickDownload'
        ],

        initialize: function (options) {
            this.parent(options);
            this.setAttribute('icon', 'fa fa-file-text-o');

            this.$Download = null;
            this.$File     = null;
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
                        title             : File.basename,
                        fileHash          : File.hash,
                        labelAttachToEmail: QUILocale.get(lg, 'controls.userFiles.SelectItem.tpl.labelAttachToEmail'),
                        titleAttachToEmail: QUILocale.get(lg, 'controls.userFiles.SelectItem.tpl.titleAttachToEmail')
                    })
                });

                this.$Download = new Element('span', {
                    'class': 'qui-elements-selectItem-download fa fa-download',
                    title  : QUILocale.get(lg, 'controls.userFiles.SelectItem.btn.download.title'),
                    events : {
                        click: this.$onClickDownload
                    }
                }).inject(this.$Destroy, 'before');

                const ParentSelect = this.getAttribute('Parent');
                ParentSelect.fireEvent('change', [ParentSelect]);

                this.$Text.getElements('input').addEvent('change', () => {
                    ParentSelect.fireEvent('change', [ParentSelect]);
                });

                this.fireEvent('itemAddCompleted', [File, this]);
            }).catch(() => {
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
            const EntryElm = this.getElm();

            if ('attachToEmail' in Options) {
                EntryElm.getElement('input[name="attachToEmail"]').checked = !!Options.attachToEmail;
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
        }
    });
});
