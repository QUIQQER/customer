/**
 * @module package/quiqqer/customer/bin/backend/controls/customer/AddressGrid
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/customer/AddressGrid', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/grid/Grid',
    'Locale',
    'Ajax',
    'Users'

], function (QUI, QUIControl, Grid, QUILocale, QUIAjax, Users) {
    "use strict";

    var lgQUIQQER = 'quiqqer/quiqqer';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/AddressGrid',

        Binds: [
            '$onInject',
            '$onGridClick',
            '$editComplete',
            'refresh'
        ],

        options: {
            userId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$User = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * create the DOMNode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.setStyles({
                height: '100%',
                width : '100%'
            });

            var Container = new Element('div', {
                styles: {
                    height: '100%',
                    width : '100%'
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                columnModel   : [{
                    header   : QUILocale.get(lgQUIQQER, 'id'),
                    dataIndex: 'id',
                    dataType : 'string',
                    width    : 60
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'salutation'),
                    dataIndex: 'salutation',
                    dataType : 'string',
                    width    : 60,
                    editable : true,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'firstname'),
                    dataIndex: 'firstname',
                    dataType : 'string',
                    width    : 100,
                    editable : true,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'lastname'),
                    dataIndex: 'lastname',
                    dataType : 'string',
                    width    : 100,
                    editable : true,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'users.user.address.table.phone'),
                    dataIndex: 'phone',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'email'),
                    dataIndex: 'mail',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'company'),
                    dataIndex: 'company',
                    dataType : 'string',
                    width    : 100,
                    editable : true,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'street'),
                    dataIndex: 'street_no',
                    dataType : 'string',
                    width    : 100,
                    editable : true,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'zip'),
                    dataIndex: 'zip',
                    dataType : 'string',
                    width    : 100,
                    editable : true,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'city'),
                    dataIndex: 'city',
                    dataType : 'string',
                    width    : 100,
                    editable : true,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get(lgQUIQQER, 'country'),
                    dataIndex: 'country',
                    dataType : 'string',
                    width    : 100
                }],
                buttons       : [{
                    name     : 'add',
                    text     : QUILocale.get(lgQUIQQER, 'users.user.address.table.btn.add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: function () {

                        }
                    }
                }, {
                    type: 'separator'
                }, {
                    name     : 'edit',
                    text     : QUILocale.get(lgQUIQQER, 'users.user.address.table.btn.edit'),
                    textimage: 'fa fa-edit',
                    disabled : true,
                    events   : {
                        onClick: function () {

                        }
                    }
                }, {
                    name     : 'delete',
                    text     : QUILocale.get(lgQUIQQER, 'users.user.address.table.btn.delete'),
                    textimage: 'fa fa-remove',
                    disabled : true,
                    events   : {
                        onClick: function () {

                        }
                    }
                }],
                editable      : true,
                editondblclick: true
            });

            this.$Grid.addEvents({
                onClick       : this.$onGridClick,
                onRefresh     : this.refresh,
                onEditComplete: this.$editComplete
            });

            // this.$Grid.setWidth(size.x - 60);
            this.$Grid.disable();

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self   = this;
            var User   = Users.get(this.getAttribute('userId'));
            var Loaded = Promise.resolve(User);

            if (!User.isLoaded()) {
                Loaded = User.load();
            }

            Loaded.then(function (User) {
                self.$User = User;
                self.refresh();
            });
        },

        /**
         * Resize the grid
         *
         * @return {Promise}
         */
        resize: function () {
            var size = this.$Elm.getSize();

            return Promise.all([
                this.$Grid.setHeight(size.y),
                this.$Grid.setWidth(size.x)
            ]);
        },

        /**
         * Refresh the address list
         */
        refresh: function () {
            var self = this;

            this.$Grid.disable();

            return this.$User.getAddressList().then(function (addressList) {
                self.$Grid.setData({
                    data: addressList
                });

                self.$Grid.enable();
                self.$onGridClick();
            });
        },

        //region button events

        /**
         * event: on grid click
         */
        $onGridClick: function () {
            var buttons = this.$Grid.getButtons(),
                sels    = this.$Grid.getSelectedIndices();

            if (!sels || !sels.length) {
                buttons.each(function (Btn) {
                    if (Btn.getAttribute('name') !== 'add') {
                        Btn.disable();
                    }
                });

                return;
            }

            buttons.each(function (Btn) {
                Btn.enable();
            });
        },

        /**
         * event: edit grid complete
         */
        $editComplete: function (data) {
            var self      = this,
                rowData   = this.$Grid.getDataByRow(data.row),
                attribute = data.columnModel.dataIndex;

            switch (attribute) {
                case 'firstname':
                case 'lastname':
                case 'company':
                case 'street_no':
                case 'zip':
                case 'city':
                case 'country':
                    rowData[attribute] = data.input.value;
                    break;
            }


            this.$Grid.disable();

            return new Promise(function (resolve, reject) {
                QUIAjax.post('ajax_users_address_save', function () {
                    self.$Grid.enable();
                    resolve();
                }, {
                    'package': 'quiqqer/customer',
                    onError  : reject,
                    aid      : parseInt(rowData.id),
                    uid      : self.getAttribute('userId'),
                    data     : JSON.encode({
                        company   : rowData.company,
                        salutation: rowData.salutation,
                        firstname : rowData.firstname,
                        lastname  : rowData.lastname,
                        street_no : rowData.street_no,
                        zip       : rowData.zip,
                        city      : rowData.city,
                        country   : rowData.country
                    })
                });
            });
        }

        //endregion
    });
});