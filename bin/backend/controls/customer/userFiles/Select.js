/**
 * Select for customer files
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/grid/Grid',

    'utils/Controls',
    'Locale',
    'Ajax'

], function(QUI, QUIControl, Grid, QUIControlUtils, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/customer';

    /**
     * @class controls/usersAndGroups/Input
     *
     * @param {Object} options
     * @param {HTMLInputElement} [Input]  - (optional), if no input given, one would be created
     *
     * @memberof! <global>
     */
    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select',

        Binds: [
            '$onInject',
            'openCustomerFiles'
        ],

        options: {
            userId: false,
            confirmItemDelete: false // item deletion requires confirmation
        },

        initialize: function(options) {
            this.parent(options);

            this.$ImportedValue = {};
            this.$Grid = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        $onInject: function() {

        },

        /**
         * trigger a users search and open a discount dropdown for selection
         *
         * @return {Promise}
         */
        searchCustomerFiles: function() {
            const value = this.$Search.value.trim();

            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_suggestSearch', resolve, {
                    'package': 'quiqqer/customer',
                    customerId: this.getAttribute('userId'),
                    searchString: value,
                    onError: reject
                });
            }).then((files) => {
                return files.map(function(File) {
                    return {
                        id: File.hash,
                        title: File.basename
                    };
                });
            });
        },

        /**
         * open customer file window
         */
        openCustomerFiles: function() {
            require([
                'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Window'
            ], (Window) => {
                new Window({
                    userId: this.getAttribute('userId'),
                    events: {
                        onSelect: (selectedFiles, Win) => {
                            for (let File of selectedFiles) {
                                this.addFile(File.hash);
                            }

                            Win.close();
                        }
                    }
                }).open();
            });
        },

        /**
         * Get file info
         *
         * @param {String} fileHash
         * @return {Promise}
         */
        $getFileData: function(fileHash) {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_get', resolve, {
                    'package': 'quiqqer/customer',
                    customerId: this.getAttribute('userId'),
                    fileHash: fileHash,
                    onError: reject
                });
            });
        },

        $triggerOnChange: function() {

        },

        /**
         * Get files
         *
         * @return {Promise}
         */
        getFiles: function() {
            const fileHashes = this.$Input.value.split(',');
            console.log(fileHashes);
            const loadItem = (fileHash) => {
                return new Promise((resolve) => {
                    const waitForItemLoad = setInterval((fileHash) => {
                        const Item = this.getElm().getElement(
                            '.quiqqer-customer-files-select-item[data-hash="' + fileHash + '"]'
                        );

                        if (!Item) {
                            return resolve(false);
                        }

                        const ItemControl = QUI.Controls.getById(
                            Item.getParent('.qui-elements-selectItem').get('data-quiid')
                        );

                        clearInterval(waitForItemLoad);

                        resolve({
                            hash: Item.get('data-hash'),
                            options: ItemControl.getItemOptions()
                        });
                    }, 200, fileHash);
                });
            };

            const itemPromises = [];

            return new Promise((resolve) => {
                for (let fileHash of fileHashes) {
                    if (fileHash) {
                        itemPromises.push(loadItem(fileHash));
                    }
                }

                Promise.all(itemPromises).then((valueItems) => {
                    resolve(JSON.encode(valueItems));
                });
            });
        },

        /**
         * Import input value string to the select
         *
         * @param {String} value
         */
        importValue: function(value) {
            if (!value) {
                return;
            }

            let entries = value;

            if (typeOf(value) === 'string') {
                entries = JSON.decode(value);
            }

            this.$ImportedValue = entries;

            for (let Entry of entries) {
                this.addItem(Entry.hash);
            }
        },

        /**
         * Event: onAddItem
         *
         * @param {Object} SelectControl
         * @param {String|Number} itemId
         * @param {Object} ItemControl
         */
        $onAddItem: function(SelectControl, itemId, ItemControl) {
            ItemControl.setAttributes({
                confirmDelete: this.getAttribute('confirmItemDelete')
            });

            ItemControl.addEvent('onItemAddCompleted', (File, SelectItemControl) => {
                for (let Entry of Object.values(this.$ImportedValue)) {
                    if (File.hash === Entry.hash) {
                        SelectItemControl.setItemOptions(Entry.options);
                        return;
                    }
                }
            });
        }
    });
});
