/**
 * List of all open items of customers
 *
 * @module package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems', [

    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/contextmenu/Item',
    'controls/grid/Grid',

    'package/quiqqer/payment-transactions/bin/backend/controls/IncomingPayments/AddPaymentWindow',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems.Total.html',
    'text!package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems.UserRecords.html',
    'css!package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems.css',
    'css!package/quiqqer/erp/bin/backend/payment-status.css'

], function (QUIPanel, QUIButton, QUIContextMenuItem, Grid, AddPaymentWindow, QUILocale, QUIAjax, Mustache,
    templateTotal, templateUserRecords) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems',

        Binds: [
            'refresh',
            'toggleTotal',
            'showTotal',
            'closeTotal',
            '$onCreate',
            '$onDestroy',
            '$onResize',
            '$onInject',
            '$onProcessChange',
            '$refreshButtonStatus',
            '$onClickShowOpenItemsList',
            '$onClickCopyProcess',
            '$onClickOpenUserRecords',
            '$onClickOpenProcess',
            '$onSearchKeyUp',
            '$refreshUserRecords',
            '$refreshUserRecordsButtons',
            '$onClickAddTransaction',
            '$onClickOpenDocument',
            '$onUserRecordsSearchKeyUp',
            '$onGridDblClick'
        ],

        initialize: function (options) {
            this.setAttributes({
                icon : 'fa fa-money',
                title: QUILocale.get(lg, 'panels.OpenItems.title')
            });

            this.parent(options);

            this.$Grid     = null;
            this.$Total    = null;
            this.$Search   = null;
            this.$Currency = null;

            this.$currentSearch = '';
            this.$searchDelay   = null;
            this.$periodFilter  = null;
            this.$loaded        = false;

            this.$GridDetails              = null;
            this.$currentRecordsUserId     = false;
            this.$UserRecordsSearch        = null;
            this.$currentUserRecordsSearch = '';
            this.$refreshTotalsOnly        = false;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize,
                onInject: this.$onInject,
                onDelete: this.$onDestroy
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            if (!this.$Grid) {
                return;
            }

            this.$currentSearch = this.$Search.value;

            var sortOn = this.$Grid.options.sortOn;

            this.showTotal();

            this.$search({
                perPage : this.$Grid.options.perPage,
                page    : this.$Grid.options.page,
                sortBy  : this.$Grid.options.sortBy,
                sortOn  : sortOn,
                search  : this.$currentSearch,
                currency: this.$Currency.getAttribute('value')
            }).then(function (result) {
                if (!this.$refreshTotalsOnly) {
                    result.grid.data = result.grid.data.map(function (entry) {
                        return self.$parseGridRow(entry);
                    });

                    this.$Grid.setData(result.grid);
                    this.$refreshButtonStatus();
                }

                this.$Total.set(
                    'html',
                    Mustache.render(templateTotal, Object.merge({}, result.totals, {
                        headerNet  : QUILocale.get(lg, 'panel.OpenItems.grid.net'),
                        headerVat  : QUILocale.get(lg, 'panel.OpenItems.grid.vat'),
                        headerGross: QUILocale.get(lg, 'panel.OpenItems.grid.gross'),
                        headerPaid : QUILocale.get(lg, 'panel.OpenItems.grid.paid'),
                        headerOpen : QUILocale.get(lg, 'panel.OpenItems.grid.open')
                    }))
                );

                if (!this.$refreshTotalsOnly) {
                    this.$currentRecordsUserId = null;
                }

                this.$refreshTotalsOnly = false;

                this.Loader.hide();
            }.bind(this)).catch(function (err) {
                console.error(err);
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * Refresh grid entry for a specific user
         *
         * @param {Number} userId
         * @return {Promise}
         */
        $refreshUserEntry: function (userId) {
            var self = this;

            return this.$search({
                userId: userId
            }).then(function (result) {
                var entries = self.$Grid.getData();

                for (var i = 0, len = entries.length; i < len; i++) {
                    var Entry = entries[i];

                    if (Entry.userId === userId) {
                        if (!result.grid.data.length) {
                            self.$Grid.deleteRow(i);
                        } else {
                            self.$Grid.setDataByRow(i, self.$parseGridRow(result.grid.data[0]));
                        }

                        break;
                    }
                }

                this.$refreshTotalsOnly = true;
                this.refresh();
            }.bind(this)).catch(function (err) {
                console.error(err);
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * refresh the button status
         * disabled or enabled
         */
        $refreshButtonStatus: function () {
            if (!this.$Grid) {
                return;
            }

            var selected = this.$Grid.getSelectedData(),
                buttons  = this.$Grid.getButtons();

            var PDF = buttons.filter(function (Button) {
                return Button.getAttribute('name') === 'showOpenItemsList';
            })[0];

            if (selected.length) {
                PDF.enable();
                return;
            }

            PDF.disable();
        },

        /**
         * event : on create
         */
        $onCreate: function () {
            var self = this;

            // currency
            this.$Currency = new QUIButton({
                name     : 'currency',
                disabled : true,
                showIcons: false,
                events   : {
                    onChange: function (Menu, Item) {
                        self.$Currency.setAttribute('value', Item.getAttribute('value'));
                        self.$Currency.setAttribute('text', Item.getAttribute('value'));
                        self.refresh();
                    }
                }
            });

            this.addButton(this.$Currency);

            this.$Search = new Element('input', {
                type       : 'search',
                name       : 'search-value',
                placeholder: QUILocale.get(lg, 'panels.OpenItems.search.placeholder'),
                styles     : {
                    'float': 'right',
                    margin : '10px 0 0 0',
                    width  : 200
                },
                events     : {
                    keyup : this.$onSearchKeyUp,
                    search: this.$onSearchKeyUp,
                    click : this.$onSearchKeyUp
                }
            });

            this.addButton({
                name  : 'search',
                icon  : 'fa fa-search',
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.refresh
                }
            });

            this.addButton(this.$Search);

            // Grid
            this.getContent().setStyles({
                padding : 10,
                position: 'relative'
            });

            var Container = new Element('div').inject(this.getContent());

            this.$Grid = new Grid(Container, {
                pagination           : true,
                serverSort           : true,
                accordion            : true,
                autoSectionToggle    : true,
                openAccordionOnClick : false,
                toggleiconTitle      : QUILocale.get(lg, 'panels.OpenItems.grid.toggle_title'),
                accordionLiveRenderer: this.$onClickOpenUserRecords,
                exportData           : true,
                exportTypes          : {
                    csv : true,
                    json: true
                },
                buttons              : [{
                    name     : 'showOpenItemsList',
                    text     : QUILocale.get(lg, 'panels.OpenItems.btn.showOpenItemsList'),
                    textimage: 'fa fa-print',
                    disabled : true,
                    events   : {
                        onClick: this.$onClickShowOpenItemsList
                    }
                }],
                columnModel          : [{
                    header         : '&nbsp;',
                    dataIndex      : 'opener',
                    dataType       : 'int',
                    width          : 30,
                    showNotInExport: true,
                    export         : false
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.userId'),
                    dataIndex: 'customerId',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.customerName'),
                    dataIndex: 'customer_name',
                    dataType : 'integer',
                    width    : 200,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.openItemsCount'),
                    dataIndex: 'open_items_count',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.net'),
                    dataIndex: 'display_net_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.vat'),
                    dataIndex: 'display_vat_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.gross'),
                    dataIndex: 'display_total_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.paid'),
                    dataIndex: 'display_paid_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.open'),
                    dataIndex: 'display_open_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    dataIndex: 'userId',
                    dataType : 'integer',
                    hidden   : true
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: function (data) {
                    self.$onGridDblClick(data, self.$Grid);
                }
            });

            this.$Total = new Element('div', {
                'class': 'openItems-total'
            }).inject(this.getContent());
        },

        /**
         * On grid cell double click
         *
         * @param {object} data - cell data
         * @param {Object} Grid
         */
        $onGridDblClick: function (data, Grid) {
            if (typeof data === 'undefined' || typeof data.cell === 'undefined') {
                return Promise.resolve();
            }

            var selected = Grid.getSelectedData();

            if (!selected.length) {
                return Promise.resolve();
            }

            var self     = this,
                Cell     = data.cell,
                rowData  = Grid.getDataByRow(data.row),
                position = Cell.getPosition();

            switch (Cell.get('data-index')) {
                case 'customer_name':
                    require([
                        'qui/controls/contextmenu/Menu',
                        'qui/controls/contextmenu/Item'
                    ], function (QUIMenu, QUIMenuItem) {
                        var Menu = new QUIMenu({
                            events: {
                                onBlur: function () {
                                    Menu.hide();
                                    Menu.destroy();
                                }
                            }
                        });

                        Menu.appendChild(
                            new QUIMenuItem({
                                icon  : 'fa fa-list-ul',
                                text  : QUILocale.get(lg, 'panel.OpenItems.grid.openOpenPositions'),
                                events: {
                                    onClick: function () {
                                        self.$Grid.accordianOpen(data.element);
                                    }
                                }
                            })
                        );

                        Menu.appendChild(
                            new QUIMenuItem({
                                icon  : 'fa fa-user-o',
                                text  : QUILocale.get(lg, 'panel.OpenItems.grid.openCustomer'),
                                events: {
                                    onClick: function () {
                                        require(['package/quiqqer/customer/bin/backend/Handler'], function (CustomerHandler) {
                                            CustomerHandler.openCustomer(rowData.userId);
                                        });
                                    }
                                }
                            })
                        );

                        Menu.inject(document.body);
                        Menu.setPosition(position.x, position.y + 30);
                        Menu.setTitle(QUILocale.get(lg, 'panel.OpenItems.grid.chooseOption'));
                        Menu.show();
                        Menu.focus();
                    });
                    break;

                default:
                    this.$Grid.accordianOpen(data.element);
            }
        },

        /**
         * event : on resize
         */
        $onResize: function () {
            if (!this.$Grid) {
                return;
            }

            var Body = this.getContent();

            if (!Body) {
                return;
            }

            var size = Body.getSize();

            this.$Grid.setHeight(size.y - 110);
            this.$Grid.setWidth(size.x - 20);
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.$loaded = true;

            QUIAjax.get([
                'package_quiqqer_currency_ajax_getAllowedCurrencies',
                'package_quiqqer_currency_ajax_getDefault'
            ], function (currencies, currency) {
                var i, len, entry, text;

                if (!currencies.length) {
                    self.refresh();
                    self.$Currency.hide();
                    return;
                }

                for (i = 0, len = currencies.length; i < len; i++) {
                    entry = currencies[i];

                    text = entry.code + ' ' + entry.sign;
                    text = text.trim();

                    self.$Currency.appendChild(
                        new QUIContextMenuItem({
                            name : entry.code,
                            value: entry.code,
                            text : text
                        })
                    );
                }

                self.$Currency.enable();
                self.$Currency.setAttribute('value', currency.code);
                self.$Currency.setAttribute('text', currency.code);

                self.refresh();
            }, {
                'package': 'quiqqer/currency'
            });

            this.$Currency.getContextMenu(function (ContextMenu) {
                ContextMenu.setAttribute('showIcons', false);
            });
        },

        //region Buttons events

        /**
         * event : on PDF Export button click
         */
        $onClickShowOpenItemsList: function () {
            var self         = this;
            var selectedData = this.$Grid.getSelectedData();

            if (!selectedData.length) {
                return;
            }

            this.Loader.show();

            require([
                'package/quiqqer/erp/bin/backend/controls/OutputDialog'
            ], function (OutputDialog) {
                new OutputDialog({
                    entityId  : selectedData[0].userId,
                    entityType: 'OpenItemsList',
                    events    : {
                        onOpen: function () {
                            self.Loader.hide();
                        }
                    }
                }).open();
            });
        },

        /**
         * event: on click open process
         *
         * @param {object} data - cell data
         * @return {Promise}
         */
        $onClickOpenProcess: function (data) {
            // @todo auf Lieferanten ummünzen

            if (typeof data !== 'undefined' &&
                typeof data.cell !== 'undefined' &&
                (data.cell.get('data-index') === 'customer_id' ||
                    data.cell.get('data-index') === 'customer_name')) {

                var self     = this,
                    Cell     = data.cell,
                    position = Cell.getPosition(),
                    rowData  = this.$Grid.getDataByRow(data.row);

                return new Promise(function (resolve) {
                    require([
                        'qui/controls/contextmenu/Menu',
                        'qui/controls/contextmenu/Item'
                    ], function (QUIMenu, QUIMenuItem) {
                        var Menu = new QUIMenu({
                            events: {
                                onBlur: function () {
                                    Menu.hide();
                                    Menu.destroy();
                                }
                            }
                        });

                        Menu.appendChild(
                            new QUIMenuItem({
                                icon  : rowData.display_type.className,
                                text  : QUILocale.get(lg, 'panels.OpenItems.contextMenu.open.process'),
                                events: {
                                    onClick: function () {
                                        self.openProcess(rowData.hash);
                                    }
                                }
                            })
                        );

                        Menu.appendChild(
                            new QUIMenuItem({
                                icon  : 'fa fa-user-o',
                                text  : QUILocale.get(lg, 'panels.OpenItems.contextMenu.open.user'),
                                events: {
                                    onClick: function () {
                                        require(['package/quiqqer/customer/bin/backend/Handler'], function (CustomerHandler) {
                                            CustomerHandler.openCustomer(rowData.customer_id);
                                        });
                                    }
                                }
                            })
                        );

                        Menu.inject(document.body);
                        Menu.setPosition(position.x, position.y + 30);
                        Menu.setTitle(rowData.id);
                        Menu.show();
                        Menu.focus();

                        resolve();
                    });
                });
            }

            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return Promise.resolve();
            }

            return this.openProcess(selected[0].id);
        },

        //endregion

        /**
         * Open an process panel
         *
         * @param {Number} processId - ID of the process
         * @return {Promise}
         */
        openProcess: function (processId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/controls/OpenItems/Process',
                    'utils/Panels'
                ], function (ProcessPanel, PanelUtils) {
                    var Panel = new ProcessPanel({
                        processId: processId,
                        '#id'    : 'process-' + processId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve(Panel);
                });
            });
        },

        /**
         * Opens a temporary process
         *
         * @param {String|Number} processId
         * @return {Promise}
         */
        openTemporaryProcess: function (processId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/customer/bin/backend/utils/Panels'
                ], function (PanelUtils) {
                    PanelUtils.openTemporaryProcess(processId).then(resolve);
                });
            });
        },

        /**
         * key up event at the search input
         *
         * @param {DOMEvent} event
         */
        $onSearchKeyUp: function (event) {
            if (event.key === 'up' ||
                event.key === 'down' ||
                event.key === 'left' ||
                event.key === 'right') {
                return;
            }

            if (this.$searchDelay) {
                clearTimeout(this.$searchDelay);
            }

            if (event.type === 'click') {
                // workaround, cancel needs time to clear
                (function () {
                    if (this.$currentSearch !== this.$Search.value) {
                        this.$searchDelay = (function () {
                            this.refresh();
                        }).delay(250, this);
                    }
                }).delay(100, this);
            }

            if (this.$currentSearch === this.$Search.value) {
                return;
            }

            if (event.key === 'enter') {
                this.$searchDelay = (function () {
                    this.refresh();
                }).delay(250, this);
                return;
            }
        },

        /**
         * Parse data entry of a grid row for display purposes
         *
         * @param {Object} Data
         * @return {Object}
         */
        $parseGridRow: function (Data) {
            Data.opener = '&nbsp;';

            if (Data.overdue) {
                Data.className = 'openItems-grid-overdue';
            }

            Data.statusBox = new Element('span', {
                'class': 'processing-status',
                html   : Data.status_title,
                styles : {
                    'border-color': Data.status_color,
                    color         : Data.status_color
                }
            });

            return Data;
        },

        /**
         * Search open item slist
         *
         * @param {Object} SearchParams
         * @return {Promise}
         */
        $search: function (SearchParams) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_OpenItemsList_search', resolve, {
                    'package'   : 'quiqqer/customer',
                    searchParams: JSON.encode(SearchParams),
                    onError     : reject
                });
            });
        },

        // region User records

        /**
         * Open the accordion details of the open items
         *
         * @param {Object} data
         */
        $onClickOpenUserRecords: function (data) {
            var self       = this,
                Row        = self.$Grid.getDataByRow(data.row),
                ParentNode = data.parent;

            if (Row.userId === this.$currentRecordsUserId) {
                return;
            }

            this.$currentRecordsUserId = Row.userId;

            ParentNode.setStyle('padding', 10);
            //ParentNode.set('html', '<div class="fa fa-spinner fa-spin"></div>');

            ParentNode.addClass('quiqqer-customer-openitems-userrecords');
            ParentNode.set('html', Mustache.render(templateUserRecords));

            var GridParent = ParentNode.getElement('.quiqqer-customer-openitems-userrecords-list');

            if (this.$GridDetails) {
                this.$GridDetails.destroy();
            }

            this.$GridDetails = new Grid(GridParent, {
                pagination          : true,
                serverSort          : true,
                accordion           : false,
                autoSectionToggle   : false,
                openAccordionOnClick: false,
                toggleiconTitle     : '',
                // @todo Export aktivieren?
                //exportData          : true,
                //exportTypes         : {
                //    csv : 'CSV',
                //    json: 'JSON'
                //},
                buttons    : [{
                    name     : 'addTransaction',
                    text     : QUILocale.get(lg, 'panels.OpenItems.details.btn.addTransaction'),
                    textimage: 'fa fa-money',
                    disabled : true,
                    events   : {
                        onClick: this.$onClickAddTransaction
                    }
                }, {
                    name     : 'open',
                    text     : QUILocale.get(lg, 'panels.OpenItems.details.btn.open'),
                    textimage: 'fa fa-file-o',
                    disabled : true,
                    events   : {
                        onClick: this.$onClickOpenDocument
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.date'),
                    dataIndex: 'date',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.documentNo'),
                    dataIndex: 'documentNo',
                    dataType : 'string',
                    width    : 125
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.documentType'),
                    dataIndex: 'documentTypeTitle',
                    dataType : 'string',
                    width    : 85
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.net'),
                    dataIndex: 'net',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.vat'),
                    dataIndex: 'vat',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.gross'),
                    dataIndex: 'gross',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.paid'),
                    dataIndex: 'paid',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.open'),
                    dataIndex: 'open',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.daysOpen'),
                    dataIndex: 'daysOpen',
                    dataType : 'integer',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.daysDue'),
                    dataIndex: 'daysDue',
                    dataType : 'integer',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.dunningLevel'),
                    dataIndex: 'dunningLevel',
                    dataType : 'integer',
                    width    : 75
                }, {
                    dataIndex: 'documentType',
                    dataType : 'string',
                    hidden   : true
                }, {
                    dataIndex: 'documentId',
                    dataType : 'string',
                    hidden   : true
                }, {
                    dataIndex: 'hash',
                    dataType : 'string',
                    hidden   : true
                }]
            });

            var GridBtnBar = this.$GridDetails.getElm().getElement('.tDiv');

            new QUIButton({
                name  : 'searchUserRecords',
                icon  : 'fa fa-search',
                styles: {
                    float : 'right',
                    margin: 4
                },
                events: {
                    onClick: function () {
                        self.$refreshUserRecords(self.$GridDetails);
                    }
                }
            }).inject(GridBtnBar);

            this.$UserRecordsSearch = new Element('input', {
                'class'    : 'quiqqer-customer-openitems-userrecords-search',
                'type'     : 'search',
                placeholder: QUILocale.get(lg, 'panels.OpenItems.details.tpl.placeholderSearch'),
            }).inject(this.$GridDetails.getElm().getElement('.tDiv'));

            this.$UserRecordsSearch.addEvents({
                keyup : this.$onUserRecordsSearchKeyUp,
                search: this.$onUserRecordsSearchKeyUp,
                click : this.$onUserRecordsSearchKeyUp
            });

            this.$GridDetails.addEvents({
                onRefresh : this.$refreshUserRecords,
                onClick   : this.$refreshUserRecordsButtons,
                onDblClick: this.$onClickAddTransaction
            });

            this.$refreshUserRecords(this.$GridDetails, true).then(function () {
                var entries = self.$GridDetails.getData();

                if (entries.length) {
                    self.$GridDetails.selectRow(self.$GridDetails.getRowElement(0));
                    self.$refreshUserRecordsButtons();
                }
            });
        },

        /**
         * Refresh grid button status of user open items records details
         */
        $refreshUserRecordsButtons: function () {
            if (!this.$GridDetails) {
                return;
            }

            var selected = this.$GridDetails.getSelectedData(),
                buttons  = this.$GridDetails.getButtons();

            var OpenDocument = buttons.filter(function (Button) {
                return Button.getAttribute('name') === 'open';
            })[0];

            var AddTransaction = buttons.filter(function (Button) {
                return Button.getAttribute('name') === 'addTransaction';
            })[0];

            if (selected.length) {
                OpenDocument.enable();
                AddTransaction.enable();
                return;
            }

            OpenDocument.disable();
            AddTransaction.disable();
        },

        /**
         * If the user clicks the "add transaction to open item record" button
         */
        $onClickAddTransaction: function () {
            var self     = this,
                selected = this.$GridDetails.getSelectedData();

            if (!selected.length) {
                return;
            }

            var Row = selected[0];
            var erpEntity;

            switch (Row.documentType) {
                case 'invoice':
                    erpEntity = 'Invoice';
                    break;

                case 'order':
                    erpEntity = 'Order';
                    break;

                default:
                    return;
            }

            const submitTransaction = function (Win, Data) {
                Win.Loader.show();

                switch (erpEntity) {
                    case 'Invoice':
                        require(['package/quiqqer/invoice/bin/Invoices'], function (Invoices) {
                            Invoices.addPaymentToInvoice(
                                Row.documentNo,
                                Data.amount,
                                Data.payment_method
                            ).then(function () {
                                Win.close();

                                self.$refreshUserEntry(self.$currentRecordsUserId).then(function () {
                                    self.$refreshUserRecords(self.$GridDetails, true);
                                });
                            }).catch(function (err) {
                                Win.Loader.hide();
                            });
                        });
                        break;

                    case 'Order':
                        require(['package/quiqqer/order/bin/backend/Orders'], function (Orders) {
                            Orders.addPaymentToOrder(
                                Row.documentId,
                                Data.amount,
                                Data.payment_method,
                                Data.date
                            ).then(function () {
                                Win.close();

                                self.$refreshUserEntry(self.$currentRecordsUserId).then(function () {
                                    self.$refreshUserRecords(self.$GridDetails, true);
                                });
                            }).catch(function (err) {
                                Win.Loader.hide();
                            });
                        });
                        break;
                }
            };

            const linkTransaction = (txId, Win) => {
                Win.Loader.show();

                switch (erpEntity) {
                    case 'Invoice':
                        require(['package/quiqqer/invoice/bin/Invoices'], (Invoices) => {
                            Invoices.linkTransaction(
                                Row.hash,
                                txId
                            ).then(() => {
                                Win.close();

                                this.$refreshUserEntry(this.$currentRecordsUserId).then(() => {
                                    this.$refreshUserRecords(this.$GridDetails, true);
                                });
                            }).catch(() => {
                                Win.Loader.hide();
                            });
                        });
                        break;

                    case 'Order':
                        require(['package/quiqqer/order/bin/backend/Orders'], (Orders) => {
                            Orders.linkTransaction(Row.hash, txId).then(() => {
                                Win.close();

                                this.$refreshUserEntry(this.$currentRecordsUserId).then(() => {
                                    this.$refreshUserRecords(this.$GridDetails, true);
                                });
                            }).catch(() => {
                                Win.Loader.hide();
                            });
                        });
                        break;
                }
            };

            new AddPaymentWindow({
                entityId  : Row.documentNo,
                entityType: erpEntity,
                events    : {
                    onSubmit        : submitTransaction,
                    onSubmitExisting: linkTransaction
                }
            }).open();
        },

        /**
         * If the user clicks the "open open item record document" button
         */
        $onClickOpenDocument: function () {
            var self     = this,
                selected = this.$GridDetails.getSelectedData();

            if (!selected.length) {
                return;
            }

            var Row = selected[0];

            switch (Row.documentType) {
                case 'invoice':
                    this.Loader.show();

                    require(['package/quiqqer/invoice/bin/backend/utils/Panels'], function (InvoicePanels) {
                        InvoicePanels.openInvoice(Row.documentNo).then(function () {
                            self.Loader.hide();
                        });
                    });
                    break;

                case 'order':
                    require(['package/quiqqer/order/bin/backend/controls/panels/Orders'], function (Orders) {
                        var Handler = new Orders();

                        Handler.openOrder(Row.documentId).then(function () {
                            self.Loader.hide();
                        });
                    });
                    break;

                default:
                    return;
            }
        },

        /**
         * key up event at the user records search input
         *
         * @param {DOMEvent} event
         */
        $onUserRecordsSearchKeyUp: function (event) {
            if (event.key === 'up' ||
                event.key === 'down' ||
                event.key === 'left' ||
                event.key === 'right') {
                return;
            }

            if (this.$searchDelay) {
                clearTimeout(this.$searchDelay);
            }

            if (event.type === 'click') {
                // workaround, cancel needs time to clear
                (function () {
                    if (this.$UserRecordsSearch.value !== this.$currentUserRecordsSearch) {
                        this.$searchDelay = (function () {
                            this.$refreshUserRecords(this.$GridDetails);
                        }).delay(250, this);
                    }
                }).delay(100, this);
            }

            if (this.$UserRecordsSearch.value === this.$currentUserRecordsSearch) {
                return;
            }

            if (event.key === 'enter') {
                this.$searchDelay = (function () {
                    this.$refreshUserRecords(this.$GridDetails);
                }).delay(250, this);
            }
        },

        /**
         * Refresh GRID with user open items records
         *
         * @param {Object} Grid
         * @param {Boolean} [forceRefresh] - Force refresh of user open items records; otherwise try
         * to fetch from cache
         *
         * @return {Promise}
         */
        $refreshUserRecords: function (Grid, forceRefresh) {
            if (!this.$GridDetails) {
                return;
            }

            forceRefresh = forceRefresh || false;

            var self   = this;
            var sortOn = this.$GridDetails.options.sortOn;

            switch (sortOn) {
                case 'supplier_name':
                case 'display_vatsum':
                case 'display_paid':
                case 'display_toPay':
                    sortOn = false;
                    break;
            }

            this.Loader.show();

            this.$currentUserRecordsSearch = this.$UserRecordsSearch.value;

            return this.$getUserOpenItems(
                this.$currentRecordsUserId,
                {
                    perPage: this.$GridDetails.options.perPage,
                    page   : this.$GridDetails.options.page,
                    sortBy : this.$GridDetails.options.sortBy,
                    sortOn : sortOn,
                    search : this.$currentUserRecordsSearch
                },
                forceRefresh
            ).then(function (result) {
                self.$GridDetails.setData(result);
                self.Loader.hide();

                self.$refreshUserRecordsButtons();
            });
        },

        /**
         * Get list of open items by user
         *
         * @param {Number} userId
         * @param {Object} SearchParams
         * @param {Boolean} forceRefresh
         * @return {Promise}
         */
        $getUserOpenItems: function (userId, SearchParams, forceRefresh) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_OpenItemsList_getUserOpenItems', resolve, {
                    'package'   : 'quiqqer/customer',
                    userId      : userId,
                    searchParams: JSON.encode(SearchParams),
                    forceRefresh: forceRefresh ? 1 : 0,
                    onError     : reject
                });
            });
        },

        // endregion

        // region Totals

        /**
         * Show the total display
         */
        showTotal: function () {
            this.getContent().setStyle('overflow', 'hidden');

            return new Promise(function (resolve) {
                this.$Total.setStyles({
                    display: 'inline-block',
                    opacity: 0
                });

                moofx(this.$Total).animate({
                    bottom : 1,
                    opacity: 1
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        }

        // endregion
    });
});
