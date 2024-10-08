/**
 * @module package/quiqqer/customer/bin/backend/controls/Administration
 * @author www.pcsg.de (Henning Leutz)
 *
 * This is the main administration control for the customer package
 *
 * Customer Administration
 * - List of customers
 * - Manage customer
 * - Search customer
 *
 * @event customerOpenBegin [self, userId]
 * @event onCustomerOpen [self, userId, Panel]
 * @event onCustomerOpenEnd [self, userId, Panel]
 * @event onListOpen [self]
 */
define('package/quiqqer/customer/bin/backend/controls/Administration', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Switch',
    'qui/controls/contextmenu/Menu',
    'qui/controls/contextmenu/Item',
    'qui/controls/windows/Prompt',
    'qui/controls/windows/Confirm',

    'package/quiqqer/customer/bin/backend/Handler',
    'controls/grid/Grid',
    'Mustache',
    'Locale',
    'Ajax',
    'Users',
    'Permissions',

    'text!package/quiqqer/customer/bin/backend/controls/Administration.html',
    'css!package/quiqqer/customer/bin/backend/controls/Administration.css'

], function(QUI, QUIControl, QUISwitch, ContextMenu, ContextMenuItem, QUIPrompt, QUIConfirm,
    CustomerHandler, Grid, Mustache, QUILocale, QUIAjax, Users, Permissions, template
) {
    'use strict';

    const lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/customer/bin/backend/controls/Administration',

        Binds: [
            '$onInject',
            '$onDestroy',
            '$onUserRefresh',
            '$onUserChange',
            '$editComplete',
            '$gridDblClick',
            '$gridDblClickBegin',
            '$gridClick',
            'refresh',
            'toggleFilter',
            'openDeleteWindow',
            'openAddWindow'
        ],

        options: {
            page: 1,
            perPage: 50,
            editable: true,
            submittable: false,
            add: true,
            customerId: false,

            showEditableButton: false
        },

        initialize: function(options) {
            this.parent(options);

            this.$SearchContainer = null;
            this.$SearchInput = null;
            this.$FilterButton = null;
            this.$customerGroup = null;

            this.$CustomerPanel = null;
            this.$GroupSwitch = null;
            this.$GridContainer = null;
            this.$Grid = null;
            this.$EditableButton = null;

            this.addEvents({
                onInject: this.$onInject,
                onDestroy: this.$onDestroy
            });

            Users.addEvents({
                onSwitchStatus: this.$onUserChange,
                onDelete: this.$onUserChange,
                onRefresh: this.$onUserRefresh,
                onSave: this.$onUserRefresh
            });
        },

        /**
         * Create the DOMNode element
         */
        create: function() {
            const self = this;

            this.$Elm = new Element('div', {
                'class': 'quiqqer-customer-administration',
                html: Mustache.render(template, {
                    searchPlaceholder: QUILocale.get(lg, 'panel.search.placeholder'),

                    title: QUILocale.get(lg, 'panel.search.title'),
                    search: QUILocale.get('quiqqer/core', 'search'),
                    userId: QUILocale.get('quiqqer/core', 'user_id'),
                    username: QUILocale.get('quiqqer/core', 'username'),
                    firstname: QUILocale.get('quiqqer/core', 'firstname'),
                    lastname: QUILocale.get('quiqqer/core', 'lastname'),
                    email: QUILocale.get('quiqqer/core', 'email'),
                    group: QUILocale.get('quiqqer/core', 'group'),
                    c_date: QUILocale.get('quiqqer/core', 'c_date'),
                    from: QUILocale.get('quiqqer/core', 'from'),
                    to: QUILocale.get('quiqqer/core', 'to')
                })
            });

            this.$SearchContainer = this.$Elm.getElement('.quiqqer-customer-administration-search');
            this.$GridContainer = this.$Elm.getElement('.quiqqer-customer-administration-grid');
            this.$SearchInput = this.$Elm.getElement('[name="search"]');
            this.$SubmitButton = this.$Elm.getElement('[name="submit"]');
            this.$FilterButton = this.$Elm.getElement('button[name="filter"]');

            this.$SearchContainer.setStyle('display', 'none');
            this.$GridContainer.setStyle('display', 'none');

            this.$SearchContainer.getElement('form').addEvent('submit', function(event) {
                event.stop();
            });

            this.$SubmitButton.addEvent('click', function() {
                self.refresh();
            });

            this.$SearchInput.addEvent('keydown', function(event) {
                if (event.key === 'enter') {
                    event.stop();
                }
            });

            this.$SearchInput.addEvent('keyup', function(event) {
                if (event.key === 'enter') {
                    self.refresh();
                }
            });

            this.$FilterButton.addEvent('click', function(event) {
                event.stop();
                self.toggleFilter();
            });

            if (!this.getAttribute('add')) {
                this.$AddButton.setStyle('display', 'none');
            }

            if (this.getAttribute('showEditableButton')) {
                this.$EditableButton = new Element('div', {
                    'class': 'quiqqer-customer-administration-editableButton',
                    'html': '<span class="fa fa-edit"></span>',
                    title: QUILocale.get(lg, 'panel.search.editable.button.title'),
                    events: {
                        click: function() {
                            const activeClass = 'quiqqer-customer-administration-editableButton--active';
                            const active = self.$EditableButton.hasClass(activeClass);

                            if (active) {
                                // deactivate
                                self.$EditableButton.removeClass(activeClass);
                                self.setAttribute('editable', false);
                            } else {
                                // activate
                                self.$EditableButton.addClass(activeClass);
                                self.setAttribute('editable', true);
                            }
                        }
                    }
                }).inject(this.$SearchContainer);
            }

            this.$GroupSwitch = new QUISwitch({
                events: {
                    onChange: this.refresh
                },
                status: true,
                styles: {
                    'float': 'right',
                    marginRight: this.getAttribute('showEditableButton') ? 0 : 20
                },
                title: QUILocale.get(lg, 'panel.search.switch.customer.group')
            }).inject(this.$SearchContainer);


            // create grid
            this.$Container = new Element('div');
            this.$Container.inject(this.$GridContainer);

            let columnModel = [];

            if (this.getAttribute('submittable')) {
                columnModel.push({
                    header: '&nbsp',
                    dataIndex: 'submit_button',
                    dataType: 'node',
                    width: 60
                });
            }

            columnModel.push({
                header: QUILocale.get(lg, 'customerId'),
                dataIndex: 'customerId',
                dataType: 'integer',
                width: 100
            });

            /*{
                header   : QUILocale.get('quiqqer/core', 'username'),
                dataIndex: 'username',
                dataType : 'integer',
                width    : 150,
                editable : editable,
                className: editable ? 'clickable' : ''
            }*/

            columnModel.push({
                header: QUILocale.get('quiqqer/core', 'company'),
                dataIndex: 'company',
                dataType: 'string',
                width: 150,
                editable: true,
                className: 'clickable'
            });

            columnModel.push({
                header: QUILocale.get('quiqqer/core', 'firstname'),
                dataIndex: 'firstname',
                dataType: 'string',
                width: 150,
                editable: true,
                className: 'clickable'
            });

            columnModel.push({
                header: QUILocale.get('quiqqer/core', 'lastname'),
                dataIndex: 'lastname',
                dataType: 'string',
                width: 150,
                editable: true,
                className: 'clickable'
            });

            columnModel.push({
                header: QUILocale.get('quiqqer/core', 'email'),
                dataIndex: 'email',
                dataType: 'string',
                width: 150,
                editable: true,
                className: 'clickable'
            });

            columnModel.push({
                header: QUILocale.get('quiqqer/core', 'group'),
                dataIndex: 'usergroup_display',
                dataType: 'string',
                editable: true,
                className: 'clickable'
            });

            columnModel.push({
                dataIndex: 'usergroup',
                dataType: 'string',
                hidden: true
            });

            columnModel.push({
                header: QUILocale.get('quiqqer/core', 'c_date'),
                dataIndex: 'regdate',
                dataType: 'date',
                width: 150
            });

            this.$Grid = new Grid(this.$Container, {
                buttons: [
                    {
                        name: 'add',
                        textimage: 'fa fa-plus',
                        text: QUILocale.get(lg, 'customer.window.create.title'),
                        events: {
                            onClick: self.openAddWindow
                        }
                    },
                    {
                        name: 'delete',
                        textimage: 'fa fa-trash',
                        text: QUILocale.get(lg, 'customer.window.delete.title'),
                        disabled: true,
                        styles: {
                            'float': 'right'
                        },
                        events: {
                            onClick: self.openDeleteWindow
                        }
                    }
                ],

                columnModel: columnModel,
                pagination: true,
                filterInput: true,
                perPage: this.getAttribute('perPage'),
                page: this.getAttribute('page'),
                sortOn: this.getAttribute('field'),
                serverSort: true,
                showHeader: true,
                sortHeader: true,
                alternaterows: true,
                resizeColumns: true,
                selectable: true,
                multipleSelection: true,
                resizeHeaderOnly: true,
                editable: true,
                editondblclick: true
            });

            // Events
            this.$Grid.addEvents({
                onClick: this.$gridClick,
                onDblClick: this.$gridDblClick,
                onDblClickBegin: this.$gridDblClickBegin,
                // onBlur    : this.$gridBlur,
                editComplete: this.$editComplete,
                refresh: this.refresh
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function() {
            const self = this;
            const customerId = this.getAttribute('customerId');

            this.$Grid.disable();

            CustomerHandler.getCustomerGroupId().then((customerGroup) => {
                self.$customerGroup = customerGroup;

                if (customerId && customerId !== '0') {
                    return this.$openCustomer(this.getAttribute('customerId'));
                }

                if (this.isInWindow() && this.$SearchInput) {
                    this.$SearchInput.focus();
                }

                this.$SearchContainer.setStyle('display', null);
                this.$GridContainer.setStyle('display', null);

                return self.refresh().then(function() {
                    self.$Grid.enable();
                });
            });
        },

        /**
         * Is the administration in a qui window?
         *
         * @return {boolean}
         */
        isInWindow: function() {
            return !!this.getElm().getParent('.qui-window-popup');
        },

        /**
         * event: on user change
         */
        $onUserRefresh: function() {
            this.refresh();
        },

        /**
         * event: on user status change
         *
         * @param Users
         * @param ids
         */
        $onUserChange: function(Users, ids) {
            let i, len;
            let data = this.$Grid.getData();

            if (typeOf(ids) === 'array') {
                let tmp = {};

                for (i = 0, len = ids.length; i < len; i++) {
                    tmp[ids[i]] = true;
                }

                ids = tmp;
            }

            for (i = 0, len = data.length; i < len; i++) {
                if (typeof ids[data[i].id] === 'undefined') {
                    continue;
                }

                // use is in list, refresh
                this.refresh();
                break;
            }
        },

        /**
         * event: on control destroy
         */
        $onDestroy: function() {
            Users.removeEvents({
                onSwitchStatus: this.$onUserChange,
                onDelete: this.$onUserChange,
                onRefresh: this.$onUserRefresh,
                onSave: this.$onUserRefresh
            });
        },

        /**
         * return all selected customer ids
         *
         * @return {Array}
         */
        getSelectedCustomerIds: function() {
            if (this.$CustomerPanel) {
                return [this.$CustomerPanel.getAttribute('userId')];
            }

            return this.$Grid.getSelectedData().map(function(entry) {
                return entry.user_uuid;
            });
        },

        /**
         * return all selected customer
         *
         * @return {Array}
         */
        getSelectedCustomer: function() {
            return this.$Grid.getSelectedData();
        },

        /**
         * Resize the grid
         *
         * @return {Promise|Promise|Promise|Promise|*}
         */
        resize: function() {
            if (!this.$Grid) {
                return Promise.resolve();
            }

            const size = this.$GridContainer.getSize();

            return Promise.all([
                this.$Grid.setHeight(size.y - 40),
                this.$Grid.setWidth(size.x - 40)
            ]);
        },

        /**
         * Refresh the grid
         */
        refresh: function() {
            const self = this,
                customerGroup = this.$GroupSwitch.getStatus(),
                options = this.$Grid.options,
                Form = this.$SearchContainer.getElement('form');

            let params = {
                perPage: options.perPage || 50,
                page: options.page || 1,
                sortBy: options.sortBy,
                sortOn: options.sortOn,
                customerGroup: customerGroup,
                search: this.$SearchInput.value,
                onlyCustomer: this.$GroupSwitch.getStatus() ? 1 : 0,
                filter: {
                    userId: Form.elements.userId.checked ? 1 : 0,
                    username: Form.elements.username.checked ? 1 : 0,
                    firstname: Form.elements.firstname.checked ? 1 : 0,
                    lastname: Form.elements.lastname.checked ? 1 : 0,
                    email: Form.elements.email.checked ? 1 : 0,
                    group: Form.elements.group.checked ? 1 : 0,
                    regdate_from: Form.elements['registration-from'].value,
                    regdate_to: Form.elements['registration-to'].value
                }
            };

            this.fireEvent('refreshBegin', [this]);

            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_customer_ajax_backend_search', function(result) {
                    const onChange = function(Switch) {
                        const userStatus = Switch.getStatus();
                        const userId = Switch.getAttribute('userId');

                        self.$setStatus(userId, userStatus).catch(function() {
                            if (userStatus) {
                                Switch.setSilentOff();
                            } else {
                                Switch.setSilentOn();
                            }
                        });
                    };

                    const edit = function(e) {
                        e.stop();

                        self.$openCustomer(e.target.get('data-userid'));
                    };

                    for (let i = 0, len = result.data.length; i < len; i++) {
                        result.data[i].status = new QUISwitch({
                            status: result.data[i].status,
                            userId: result.data[i].user_uuid,
                            events: {
                                onChange: onChange
                            }
                        });

                        if (self.getAttribute('submittable')) {
                            result.data[i].submit_button = new Element('span', {
                                'class': 'fa fa-edit',
                                title: QUILocale.get(lg, 'window.customer.edit.button'),
                                'data-userid': result.data[i].user_uuid,
                                events: {
                                    click: edit
                                },
                                styles: {
                                    cursor: 'pointer',
                                    textAlign: 'center',
                                    width: 50
                                }
                            });
                        }
                    }

                    self.$Grid.setData(result);
                    self.fireEvent('refreshEnd', [self]);
                    resolve();
                }, {
                    package: 'quiqqer/customer',
                    params: JSON.encode(params)
                });
            });
        },

        /**
         * event: grid edit complete
         *
         * @param data
         */
        $editComplete: function(data) {
            const self = this,
                rowData = this.$Grid.getDataByRow(data.row),
                attributes = {},
                attribute = data.columnModel.dataIndex;

            switch (attribute) {
                case 'firstname':
                case 'lastname':
                case 'email':
                case 'username':
                    attributes[attribute] = data.input.value;
                    break;
            }

            this.$Grid.disable();

            return new Promise(function(resolve) {
                QUIAjax.post('package_quiqqer_customer_ajax_backend_customer_instantSave', function() {
                    self.$Grid.enable();
                    resolve();
                }, {
                    'package': 'quiqqer/customer',
                    userId: rowData.customerId,
                    data: JSON.encode(attributes),
                    onError: function() {
                        rowData[attribute] = data.oldvalue;
                        self.$Grid.setDataByRow(data.row, rowData);
                        self.$Grid.enable();
                    }
                });
            });
        },

        /**
         * Set user active status
         *
         * @param userId
         * @param status
         * @return {Promise}
         */
        $setStatus: function(userId, status) {
            const self = this;

            this.$Grid.disable();

            return new Promise(function(resolve, reject) {
                QUIAjax.post('ajax_users_save', function() {
                    self.$Grid.enable();
                    resolve();
                }, {
                    uid: userId,
                    attributes: JSON.encode({
                        active: status
                    }),
                    onError: function(err) {
                        self.$Grid.enable();
                        reject(err);
                    }
                });
            });
        },

        /**
         * add the user to the customer group
         *
         * @param userId
         */
        $addToCustomer: function(userId) {
            this.$Grid.disable();

            CustomerHandler.addToCustomer(userId).then(function() {
                this.refresh();
                this.$Grid.enable();
            }.bind(this));
        },

        /**
         * remove the user to the customer group
         *
         * @param userId
         */
        $removeFromCustomer: function(userId) {
            this.$Grid.disable();

            CustomerHandler.removeFromCustomer(userId).then(function() {
                this.refresh();
                this.$Grid.enable();
            }.bind(this));
        },

        /**
         * opens the user group edit window
         *
         * @param userId
         */
        $editUserGroups: function(userId) {
            const self = this;

            require([
                'package/quiqqer/customer/bin/backend/controls/users/UserGroupsWindow'
            ], function(Window) {
                new Window({
                    userId: userId,
                    events: {
                        onSubmit: self.refresh
                    }
                }).open();
            });
        },

        /**
         * Opens the Customer
         *
         * @param userId
         */
        $openCustomer: function(userId) {
            if (!userId) {
                return;
            }

            const self = this;

            this.fireEvent('customerOpenBegin', [
                this,
                userId
            ]);

            if (!self.isInWindow()) {
                require([
                    'utils/Panels',
                    'package/quiqqer/customer/bin/backend/controls/customer/Panel'
                ], (PanelUtils, Panel) => {
                    PanelUtils.openPanelInTasks(
                        new Panel({
                            userId: userId
                        })
                    );
                });

                return;
            }

            moofx([
                this.$SearchContainer,
                this.$GridContainer
            ]).animate({
                opacity: 0
            }, {
                duration: 200,
                callback: () => {
                    this.$SearchContainer.setStyle('display', 'none');
                    this.$GridContainer.setStyle('display', 'none');
                }
            });


            require([
                'package/quiqqer/customer/bin/backend/controls/customer/Panel'
            ], function(Panel) {
                const Container = new Element('div', {
                    'class': 'quiqqer-customer-administration-customer',
                    styles: {
                        opacity: 0
                    }
                }).inject(self.getElm());

                self.$CustomerPanel = new Panel({
                    header: false,
                    userId: userId,
                    showUserButton: true,
                    showDeleteButton: false,
                    'hide-loader': true,
                    events: {
                        onLoaded: () => {
                            self.fireEvent('customerOpenEnd', [
                                this,
                                userId,
                                self.$CustomerPanel
                            ]);

                            moofx(Container).animate({
                                opacity: 1
                            });
                        },
                        onError: (Instance) => {
                            if (!Instance.$User) {
                                self.setAttribute('customerId', false);
                            }
                        }
                    }
                });

                self.$CustomerPanel.inject(Container);

                self.fireEvent('customerOpen', [
                    this,
                    userId,
                    self.$CustomerPanel
                ]);

                self.$CustomerPanel.fireEvent('show');
            });
        },

        /**
         * Close the customer panel, if a customer panel exist
         */
        closeCustomer: function() {
            if (!this.$CustomerPanel) {
                return;
            }

            const Container = this.$CustomerPanel.getElm().getParent();
            const hiedCustomer = new Promise((resolve) => {
                moofx(Container).animate({
                    opacity: 0
                }, {
                    callback: resolve
                });
            });

            this.$SearchContainer.setStyle('display', null);
            this.$SearchContainer.setStyle('opacity', null);

            this.$GridContainer.setStyle('display', null);
            this.$GridContainer.setStyle('opacity', null);

            Promise.all([
                this.refresh(),
                hiedCustomer
            ]).then(() => {
                this.$Grid.enable();

                this.$CustomerPanel = null;
                Container.destroy();
            });
        },

        /**
         *
         * @param event
         * @param GridInstance
         */
        $gridDblClickBegin: function(event, GridInstance) {
            if (!this.getAttribute('editable')) {
                GridInstance.setAttribute('editable', false);
            } else {
                GridInstance.setAttribute('editable', true);
            }
        },

        /**
         * event: on dbl click at grid
         *
         * @param {object} data - cell data
         */
        $gridDblClick: function(data) {
            if (typeof data === 'undefined' && typeof data.cell === 'undefined') {
                return;
            }

            const isInWindow = !!this.getElm().getParent('.qui-window-popup');
            const rowData = this.$Grid.getDataByRow(data.row);

            // if no editable, every dbl click opens the customer
            if (!this.getAttribute('editable')) {
                if (isInWindow) {
                    this.fireEvent('submit', [this]);
                    return;
                }

                this.$openCustomer(rowData.user_uuid);
                return;
            }

            if (data.cell.get('data-index') === 'customerId' || data.cell.get('data-index') === 'regdate') {
                if (isInWindow) {
                    this.fireEvent('submit', [this]);
                    return;
                }

                this.$openCustomer(rowData.user_uuid);
                return;
            }

            if (this.getAttribute('editable') === false &&
                (data.cell.get('data-index') === 'firstname' ||
                    data.cell.get('data-index') === 'lastname' ||
                    data.cell.get('data-index') === 'usergroup_display' ||
                    data.cell.get('data-index') === 'email')) {
                if (isInWindow) {
                    this.fireEvent('submit', [this]);
                    return;
                }

                this.$openCustomer(rowData.user_uuid);
                return;
            }


            if (data.cell.get('data-index') === 'usergroup_display') {
                const self = this,
                    Cell = data.cell,
                    position = Cell.getPosition();

                const Menu = new ContextMenu({
                    events: {
                        onBlur: function() {
                            Menu.hide();
                            Menu.destroy();
                        }
                    }
                });

                if (rowData.usergroup.indexOf(this.$customerGroup) === -1) {
                    Menu.appendChild(
                        new ContextMenuItem({
                            icon: 'fa fa-user-o',
                            text: QUILocale.get(lg, 'administration.contextMenu.add.to.customer'),
                            events: {
                                onClick: function() {
                                    self.$addToCustomer(rowData.customerId);
                                }
                            }
                        })
                    );
                } else {
                    Menu.appendChild(
                        new ContextMenuItem({
                            icon: 'fa fa-user-o',
                            text: QUILocale.get(lg, 'administration.contextMenu.remove.from.customer'),
                            events: {
                                onClick: function() {
                                    self.$removeFromCustomer(rowData.customerId);
                                }
                            }
                        })
                    );
                }

                Menu.appendChild(
                    new ContextMenuItem({
                        icon: 'fa fa-users',
                        text: QUILocale.get(lg, 'administration.contextMenu.groups'),
                        events: {
                            onClick: function() {
                                self.$editUserGroups(rowData.customerId);
                            }
                        }
                    })
                );

                Menu.appendChild(
                    new ContextMenuItem({
                        icon: 'fa fa-user',
                        text: QUILocale.get(lg, 'administration.contextMenu.user'),
                        events: {
                            onClick: function() {
                                self.$openCustomer(rowData.user_uuid);
                            }
                        }
                    })
                );

                Menu.inject(document.body);
                Menu.setPosition(position.x, position.y + 30);
                Menu.setTitle(rowData.customerId);
                Menu.show();
                Menu.focus();
            }
        },

        /**
         * event: on grid click
         */
        $gridClick: function() {
            const selected = this.$Grid.getSelectedData();
            const Delete = this.$Grid.getButtons().filter(function(Btn) {
                return Btn.getAttribute('name') === 'delete';
            })[0];

            Delete.disable();

            if (selected.length === 1) {
                Delete.enable();
            }

            this.fireEvent('onCustomerSelect', [
                this,
                selected
            ]);
        },

        /**
         * opens the add customer window
         */
        openAddWindow: function() {
            const self = this;

            require([
                'package/quiqqer/customer/bin/backend/controls/create/CustomerWindow'
            ], function(CustomerWindow) {
                new CustomerWindow({
                    events: {
                        onSubmit: function(Instance, customerId) {
                            self.refresh();
                            self.$openCustomer(customerId);
                        }
                    }
                }).open();
            });
        },

        /**
         * opens the customer delete window
         */
        openDeleteWindow: function() {
            const self = this;

            new QUIConfirm({
                title: QUILocale.get(lg, 'customer.window.delete.title'),
                text: QUILocale.get(lg, 'customer.window.delete.text'),
                information: QUILocale.get(lg, 'customer.window.delete.information'),
                icon: 'fa fa-trash',
                texticon: 'fa fa-trash',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        const selected = self.$Grid.getSelectedData().map(function(entry) {
                            return entry.user_uuid;
                        });

                        Users.deleteUsers(selected).then(function() {
                            Win.close();
                            self.refresh();
                        });
                    }
                }
            }).open();
        },

        //region filter

        /**
         * Toggle the filter
         */
        toggleFilter: function() {
            const FilterContainer = this.getElm().getElement('.quiqqer-customer-administration-search-filter');

            if (FilterContainer.getStyle('display') === 'none') {
                this.openFilter();
            } else {
                this.closeFilter();
            }
        },

        /**
         * Open the filter
         */
        openFilter: function() {
            const self = this,
                FilterContainer = this.getElm().getElement('.quiqqer-customer-administration-search-filter');

            FilterContainer.setStyle('position', 'absolute');
            FilterContainer.setStyle('opacity', 0);
            FilterContainer.setStyle('overflow', 'hidden');

            // reset
            FilterContainer.setStyle('display', null);
            FilterContainer.setStyle('height', null);
            FilterContainer.setStyle('paddingBottom', null);
            FilterContainer.setStyle('paddingTop', null);

            const height = FilterContainer.getSize().y;

            FilterContainer.setStyle('height', 0);
            FilterContainer.setStyle('paddingBottom', 0);
            FilterContainer.setStyle('paddingTop', 0);
            FilterContainer.setStyle('position', null);

            moofx(FilterContainer).animate({
                height: height,
                marginTop: 20,
                opacity: 1,
                paddingBottom: 10,
                paddingTop: 10
            }, {
                duration: 300,
                callback: function() {
                    self.resize();
                }
            });
        },

        /**
         * Close the filter
         */
        closeFilter: function() {
            const self = this,
                FilterContainer = this.getElm().getElement('.quiqqer-customer-administration-search-filter');

            moofx(FilterContainer).animate({
                height: 0,
                marginTop: 0,
                opacity: 1,
                paddingBottom: 0,
                paddingTop: 0
            }, {
                duration: 300,
                callback: function() {
                    FilterContainer.setStyle('display', 'none');

                    FilterContainer.setStyle('height', null);
                    FilterContainer.setStyle('paddingBottom', null);
                    FilterContainer.setStyle('paddingTop', null);

                    self.resize();
                }
            });
        }

        //endregion
    });
});
