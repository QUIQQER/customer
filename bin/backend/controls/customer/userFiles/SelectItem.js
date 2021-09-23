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
            '$getFileData'
        ],

        initialize: function (options) {
            this.parent(options);
            this.setAttribute('icon', 'fa fa-file-text-o');
        },

        /**
         * event : on inject
         */
        refresh: function () {
            this.$Text.set({
                html: '<span class="fa fa-spinner fa-spin"></span>'
            });

            this.$getFileData(this.getAttribute('id')).then((File) => {
                this.$Text.set({
                    html: Mustache.render(templateEntry, {
                        title             : File.basename,
                        fileHash          : File.hash,
                        labelAttachToEmail: QUILocale.get(lg, 'controls.userFiles.SelectItem.tpl.labelAttachToEmail'),
                        titleAttachToEmail: QUILocale.get(lg, 'controls.userFiles.SelectItem.tpl.titleAttachToEmail')
                    })
                });

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
        }
    });
});
