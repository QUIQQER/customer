/**
 * List of all open items of customers
 *
 * @module package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Separator',
    'qui/controls/buttons/Select',
    'qui/controls/contextmenu/Item',
    'controls/grid/Grid',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems.Total.html',
    'css!package/quiqqer/customer/bin/backend/controls/OpenItems/OpenItems.css',
    'css!package/quiqqer/erp/bin/backend/payment-status.css'

], function (QUI, QUIPanel, QUIButton, QUISeparator, QUISelect, QUIContextMenuItem, Grid,
             QUILocale, QUIAjax, Mustache, templateTotal) {
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
            '$onPDFExportButtonClick',
            '$onClickCopyProcess',
            '$onClickOpenItemsDetails',
            '$onClickOpenProcess',
            '$onSearchKeyUp'
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

            this.$GridDetails = null;

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

            var status = '';
            var from   = '',
                to     = '';

            this.$currentSearch = this.$Search.value;

            if (this.$currentSearch !== '') {
                this.disableFilter();
            } else {
                this.enableFilter();
            }

            var sortOn = this.$Grid.options.sortOn;

            switch (sortOn) {
                case 'supplier_name':
                case 'display_vatsum':
                case 'display_paid':
                case 'display_toPay':
                    sortOn = false;
                    break;
            }

            this.$search({
                perPage: this.$Grid.options.perPage,
                page   : this.$Grid.options.page,
                sortBy : this.$Grid.options.sortBy,
                sortOn : sortOn,
                search : this.$currentSearch,
                filters: {
                    from    : from,
                    to      : to,
                    status  : status,
                    currency: this.$Currency.getAttribute('value')
                }
            }).then(function (result) {
                result.grid.data = result.grid.data.map(function (entry) {
                    return self.$parseGridRow(entry);
                });

                this.$Grid.setData(result.grid);
                this.$refreshButtonStatus();

                this.$Total.set(
                    'html',
                    Mustache.render(templateTotal, result.total)
                );

                this.Loader.hide();
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
                return Button.getAttribute('name') === 'printPdf';
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

            var Separator = new QUISeparator();

            this.addButton(Separator);

            Separator.getElm().setStyles({
                'float': 'right'
            });

            this.$Search = new Element('input', {
                type       : 'search',
                name       : 'search-value',
                placeholder: QUILocale.get(lg, 'panels.OpenItems.search.placeholder'),
                styles     : {
                    'float': 'left',
                    margin : '10px 0 0 0',
                    width  : 200
                },
                events     : {
                    keyup : this.$onSearchKeyUp,
                    search: this.$onSearchKeyUp,
                    click : this.$onSearchKeyUp
                }
            });

            this.addButton(this.$Search);

            this.addButton({
                name  : 'search',
                icon  : 'fa fa-search',
                styles: {
                    'float': 'left'
                },
                events: {
                    onClick: this.refresh
                }
            });

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
                autoSectionToggle    : false,
                openAccordionOnClick : false,
                toggleiconTitle      : '',
                accordionLiveRenderer: this.$onClickOpenItemsDetails,
                exportData           : true,
                exportTypes          : {
                    csv : 'CSV',
                    json: 'JSON'
                },
                buttons              : [{
                    name     : 'addTransaction',
                    text     : QUILocale.get(lg, 'panels.OpenItems.btn.addTransaction'),
                    textimage: 'fa fa-file-o',
                    disabled : true,
                    events   : {
                        onClick: this.$onClickOpenProcess
                    }
                }, {
                    name     : 'printPdf',
                    text     : QUILocale.get(lg, 'panels.OpenItems.btn.pdf'),
                    textimage: 'fa fa-print',
                    disabled : true,
                    events   : {
                        onClick: this.$onPDFExportButtonClick
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
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.netSum'),
                    dataIndex: 'display_net_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.vatSum'),
                    dataIndex: 'display_vat_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.totalSum'),
                    dataIndex: 'display_total_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.paidSum'),
                    dataIndex: 'display_paid_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.openSum'),
                    dataIndex: 'display_open_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: this.$onClickOpenProcess
            });


            this.$Total = new Element('div', {
                'class': 'openItems-total'
            }).inject(this.getContent());
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

            this.$Grid.setHeight(size.y - 20);
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

                if (!currencies.length || currencies.length === 1) {
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
            }, {
                'package': 'quiqqer/currency'
            });

            this.$Currency.getContextMenu(function (ContextMenu) {
                ContextMenu.setAttribute('showIcons', false);
            });
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function () {
            Processes.removeEvents({
                onPostProcess: this.$onProcessChange
            });
        },

        /**
         * event: A change was made to a purchasing process
         *
         * Update single processes in table without refreshing everything
         *
         * @param {Object} ProcessesHandler
         * @param {Number|Array} processIds
         */
        $onProcessChange: function (ProcessesHandler, processIds) {
            if (!this.$Grid) {
                return;
            }

            var self    = this,
                rows    = this.$Grid.getData(),
                IdToRow = {};

            var i, j, len, jlen, processId;

            if (typeof processIds === 'string' || typeof processIds === 'number') {
                processIds = [processIds];
            }

            for (i = 0, len = processIds.length; i < len; i++) {
                processId = processIds[i];

                for (j = 0, jlen = rows.length; j < jlen; j++) {
                    if (rows[j].id == processId) {
                        IdToRow[processId] = j;
                        break;
                    }
                }
            }

            if (!Object.getLength(IdToRow)) {
                return;
            }

            return ProcessesHandler.getProcessList({
                ids: processIds,
            }).then(function (result) {
                for (i = 0, len = result.grid.data.length; i < len; i++) {
                    var processId = result.grid.data[i].id;

                    if (processId in IdToRow) {
                        self.$Grid.setDataByRow(IdToRow[processId], self.$parseGridRow(result.grid.data[i]));
                    }
                }
            });
        },

        //region Buttons events

        /**
         * event : on PDF Export button click
         */
        $onPDFExportButtonClick: function (Button) {
            var selectedData = this.$Grid.getSelectedData();

            if (!selectedData.length) {
                return;
            }

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/erp/bin/backend/controls/OutputDialog'
                ], function (OutputDialog) {
                    new OutputDialog({
                        entityId  : selectedData[0].id,
                        entityType: 'PurchasingProcess',
                        events    : {
                            onOpen: function (SubmitData) {
                                Button.setAttribute('textimage', 'fa fa-print');
                                resolve();
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         * Create a copy of a process as a draft
         */
        $onClickCopyProcess: function () {
            var self     = this,
                selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return Promise.resolve(false);
            }

            DialogUtils.openCopyDialog(selected[0].id_str).then(function (newId) {
                ProcessPanels.openProcessDraft(newId);
            });
        },

        /**
         * Open the accordion details of the open items
         *
         * @param {Object} data
         */
        $onClickOpenItemsDetails: function (data) {
            var self       = this,
                Row        = self.$Grid.getDataByRow(data.row),
                ParentNode = data.parent;

            ParentNode.setStyle('padding', 10);
            //ParentNode.set('html', '<div class="fa fa-spinner fa-spin"></div>');

            ParentNode.addClass('quiqqer-customer-openitems-details');

            this.$GridDetails = new Grid(ParentNode, {
                pagination          : true,
                serverSort          : false,
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
                    text     : QUILocale.get(lg, 'panels.OpenItems.btn.addTransaction'),
                    textimage: 'fa fa-file-o',
                    disabled : true,
                    events   : {
                        onClick: this.$onClickAddTransaction
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.userId'),
                    dataIndex: 'date',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.customerName'),
                    dataIndex: 'documentTypeTitle',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.customerName'),
                    dataIndex: 'documentNo',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.netSum'),
                    dataIndex: 'net',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.vatSum'),
                    dataIndex: 'vat',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.totalSum'),
                    dataIndex: 'gross',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.paidSum'),
                    dataIndex: 'paid',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'panel.OpenItems.grid.details.openSum'),
                    dataIndex: 'open',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }]
            });

            this.$GridDetails.addEvents({
                //onRefresh : this.refresh,
                //onClick   : this.$refreshButtonStatus,
                //onDblClick: this.$onClickOpenProcess
            });

            this.Loader.show();

            this.$getUserOpenItems(Row.userId).then(function (result) {

                console.log(result);

                self.$GridDetails.setData(result);
                self.Loader.hide();

                var size = ParentNode.getSize();

                self.$GridDetails.setHeight(size.y - 120);
                self.$GridDetails.setWidth(size.x - 20);
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
         * Add a payment to an process
         *
         * @param {String|Number} hash
         * @param {String|Number} amount
         * @param {String} paymentMethod
         * @param {String|Number} date
         *
         * @return {Promise}
         */
        addPayment: function (hash, amount, paymentMethod, date) {
            var self = this;

            this.Loader.show();

            return Processes.addPaymentToProcess(
                hash,
                amount,
                paymentMethod,
                date
            ).then(function () {
                return self.refresh();
            }).then(function () {
                self.Loader.hide();
            }).catch(function (err) {
                console.error(err);
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
         * Disable the filter
         */
        disableFilter: function () {
        },

        /**
         * Enable the filter
         */
        enableFilter: function () {
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

        /**
         * Get list of open items by user
         *
         * @param {Number} userId
         * @return {Promise}
         */
        $getUserOpenItems: function (userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_OpenItemsList_getUserOpenItems', resolve, {
                    'package': 'quiqqer/customer',
                    userId   : userId,
                    onError  : reject
                });
            });
        }
    });
});
