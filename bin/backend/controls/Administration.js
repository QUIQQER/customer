/**
 * @module package/quiqqer/customer/bin/backend/controls/Administration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/Administration', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Switch',
    'controls/grid/Grid',
    'Mustache',
    'Locale',
    'Ajax',

    'text!package/quiqqer/customer/bin/backend/controls/Administration.html',
    'css!package/quiqqer/customer/bin/backend/controls/Administration.css'

], function (QUI, QUIControl, QUISwitch, Grid, Mustache, QUILocale, QUIAjax, template) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/Administration',

        Binds: [
            '$onInject',
            '$editComplete',
            'refresh'
        ],

        options: {
            page    : 1,
            perPage : 50,
            editable: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$SearchContainer = null;
            this.$SearchInput     = null;

            this.$GroupSwitch   = null;
            this.$GridContainer = null;
            this.$Grid          = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DOMNode element
         */
        create: function () {
            var self     = this,
                editable = this.getAttribute('editable');

            this.$Elm = new Element('div', {
                'class': 'quiqqer-customer-administration',
                html   : Mustache.render(template, {
                    searchPlaceholder: QUILocale.get(lg, 'panel.search.placeholder')
                })
            });

            this.$SearchContainer = this.$Elm.getElement('.quiqqer-customer-administration-search');
            this.$GridContainer   = this.$Elm.getElement('.quiqqer-customer-administration-grid');
            this.$SearchInput     = this.$Elm.getElement('[name="search"]');

            this.$SearchContainer.getElement('form').addEvent('submit', function (event) {
                event.stop();
                self.refresh();
            });

            this.$GroupSwitch = new QUISwitch({
                events: {
                    onChange: this.refresh
                },
                status: true,
                styles: {
                    'float'  : 'right',
                    marginTop: 5
                },
                title : QUILocale.get(lg, 'panel.search.switch.customer.group')
            }).inject(this.$SearchContainer);

            // create grid
            this.$Container = new Element('div');
            this.$Container.inject(this.$GridContainer);

            this.$Grid = new Grid(this.$Container, {
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/quiqqer', 'status'),
                    dataIndex: 'status',
                    dataType : 'QUI',
                    width    : 60
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'user_id'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'username'),
                    dataIndex: 'username',
                    dataType : 'integer',
                    width    : 150,
                    editable : editable,
                    className: editable ? 'clickable' : ''
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'firstname'),
                    dataIndex: 'firstname',
                    dataType : 'string',
                    width    : 150,
                    editable : editable,
                    className: editable ? 'clickable' : ''
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'lastname'),
                    dataIndex: 'lastname',
                    dataType : 'string',
                    width    : 150,
                    editable : editable,
                    className: editable ? 'clickable' : ''
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'email'),
                    dataIndex: 'email',
                    dataType : 'string',
                    width    : 150,
                    editable : editable,
                    className: editable ? 'clickable' : ''
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'group'),
                    dataIndex: 'usergroup',
                    dataType : 'integer',
                    width    : 150
                }, {
                    header   : QUILocale.get('quiqqer/quiqqer', 'c_date'),
                    dataIndex: 'regdate',
                    dataType : 'date',
                    width    : 150
                }],
                pagination       : true,
                filterInput      : true,
                perPage          : this.getAttribute('perPage'),
                page             : this.getAttribute('page'),
                sortOn           : this.getAttribute('field'),
                serverSort       : true,
                showHeader       : true,
                sortHeader       : true,
                alternaterows    : true,
                resizeColumns    : true,
                selectable       : true,
                multipleSelection: true,
                resizeHeaderOnly : true,
                editable         : true,
                editondblclick   : true
            });

            // Events
            this.$Grid.addEvents({
                // onClick   : this.$gridClick,
                // onDblClick: this.$gridDblClick,
                // onBlur    : this.$gridBlur,
                editComplete: this.$editComplete,
                refresh     : this.refresh
            });

            return this.$Elm;
        },

        $onInject: function () {
            this.refresh();
        },

        /**
         * Resize the grid
         *
         * @return {Promise|Promise|Promise|Promise|*}
         */
        resize: function () {
            if (!this.$Grid) {
                return Promise.resolve();
            }

            var size = this.$GridContainer.getSize();

            return Promise.all([
                this.$Grid.setHeight(size.y - 40),
                this.$Grid.setWidth(size.x - 40)
            ]);
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            var self          = this,
                customerGroup = this.$GroupSwitch.getStatus(),
                options       = this.$Grid.options;

            var params = {
                perPage      : options.perPage || 50,
                page         : options.page || 1,
                sortBy       : options.sortBy,
                sortOn       : options.sortOn,
                customerGroup: customerGroup,
                search       : this.$SearchInput.value
            };

            this.fireEvent('refreshBegin', [this]);

            QUIAjax.get('package_quiqqer_customer_ajax_backend_search', function (result) {
                var onChange = function (Switch) {
                    var userStatus = Switch.getStatus();
                    var userId     = Switch.getAttribute('userId');

                    self.$setStatus(userId, userStatus);
                };

                for (var i = 0, len = result.data.length; i < len; i++) {
                    result.data[i].status = new QUISwitch({
                        status: result.data[i].status,
                        userId: result.data[i].id,
                        events: {
                            onChange: onChange
                        }
                    });
                }

                self.$Grid.setData(result);
                self.fireEvent('refreshEnd', [self]);
            }, {
                package: 'quiqqer/customer',
                params : JSON.encode(params)
            });
        },

        /**
         *
         * @param data
         */
        $editComplete: function (data) {
            var self       = this,
                rowData    = this.$Grid.getDataByRow(data.row),
                attributes = {},
                attribute  = data.columnModel.dataIndex;

            switch (attribute) {
                case 'firstname':
                case 'lastname':
                case 'email':
                case 'username':
                    attributes[attribute] = data.input.value;
                    break;
            }

            this.$Grid.disable();

            return new Promise(function (resolve) {
                QUIAjax.post('ajax_users_save', function () {
                    self.$Grid.enable();
                    resolve();
                }, {
                    uid       : rowData.id,
                    attributes: JSON.encode(attributes),
                    onError   : function () {
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
        $setStatus: function (userId, status) {
            var self = this;

            this.$Grid.disable();

            return new Promise(function (resolve) {
                QUIAjax.post('ajax_users_save', function () {
                    self.$Grid.enable();
                    resolve();
                }, {
                    uid       : userId,
                    attributes: JSON.encode({
                        active: status
                    })
                });
            });
        }
    });
});