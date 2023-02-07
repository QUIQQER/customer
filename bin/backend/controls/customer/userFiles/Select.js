/**
 * Select for customer files
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select', [

    'qui/QUI',
    'qui/controls/elements/Select',

    'utils/Controls',
    'Locale',
    'Ajax'

], function (QUI, QUIElementSelect, QUIControlUtils, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/customer';

    /**
     * @class controls/usersAndGroups/Input
     *
     * @param {Object} options
     * @param {HTMLInputElement} [Input]  - (optional), if no input given, one would be created
     *
     * @memberof! <global>
     */
    return new Class({

        Extends: QUIElementSelect,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select',

        Binds: [
            'searchCustomerFiles',
            '$onSearchButtonClick',
            '$onAddItem'
        ],

        options: {
            userId           : false,
            confirmItemDelete: false // item deletion requires confirmation
        },

        initialize: function (options) {
            this.parent(options);

            this.$ImportedValue = {};

            this.setAttribute('Search', this.searchCustomerFiles);
            this.setAttribute('icon', 'fa fa-search');
            this.setAttribute('child', 'package/quiqqer/customer/bin/backend/controls/customer/userFiles/SelectItem');

            this.setAttribute(
                'placeholder',
                QUILocale.get(lg, 'controls.userFiles.Select.search_placeholder')
            );

            this.addEvents({
                onSearchButtonClick: this.$onSearchButtonClick,
                onAddItem          : this.$onAddItem
            });
        },

        /**
         * trigger a users search and open a discount dropdown for selection
         *
         * @method package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select#search
         * @return {Promise}
         */
        searchCustomerFiles: function () {
            const value = this.$Search.value.trim();

            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_files_suggestSearch', resolve, {
                    'package'   : 'quiqqer/customer',
                    customerId  : this.getAttribute('userId'),
                    searchString: value,
                    onError     : reject
                });
            }).then((files) => {
                return files.map(function (File) {
                    return {
                        id   : File.hash,
                        title: File.basename
                    };
                });
            });
        },

        /**
         * event : on search button click
         *
         * @param {Object} self - select object
         * @param {Object} Btn - button object
         */
        $onSearchButtonClick: function (self, Btn) {
            Btn.setAttribute('icon', 'fa fa-spinner fa-spin');

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Window'
            ], (Window) => {
                new Window({
                    userId: this.getAttribute('userId'),
                    events: {
                        onSelect: (selectedFiles, Win) => {
                            for (let File of selectedFiles) {
                                this.addItem(File.hash);
                            }

                            Win.close();
                        }
                    }
                }).open();

                Btn.setAttribute('icon', 'fa fa-list-alt');
            });
        },

        /**
         * Get files
         *
         * @return {Promise}
         */
        getFiles: function () {
            const fileHashes = this.$Input.value.split(',');

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
                            hash   : Item.get('data-hash'),
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
        importValue: function (value) {
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
        $onAddItem: function (SelectControl, itemId, ItemControl) {
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
