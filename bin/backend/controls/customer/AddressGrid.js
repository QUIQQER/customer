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
            '$onGridDblClick',
            '$editComplete',
            '$clickDelete',
            '$clickCreate',
            '$clickEdit',
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

            const Container = new Element('div', {
                styles: {
                    height: '100%',
                    width : '100%'
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                columnModel   : [
                    {
                        header   : QUILocale.get(lgQUIQQER, 'id'),
                        dataIndex: 'id',
                        dataType : 'string',
                        width    : 60
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'salutation'),
                        dataIndex: 'salutation',
                        dataType : 'string',
                        width    : 60,
                        editable : true,
                        className: 'clickable'
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'firstname'),
                        dataIndex: 'firstname',
                        dataType : 'string',
                        width    : 100,
                        editable : true,
                        className: 'clickable'
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'lastname'),
                        dataIndex: 'lastname',
                        dataType : 'string',
                        width    : 100,
                        editable : true,
                        className: 'clickable'
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'users.user.address.table.phone'),
                        dataIndex: 'phone',
                        dataType : 'string',
                        width    : 100
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'email'),
                        dataIndex: 'mail',
                        dataType : 'string',
                        width    : 100
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'company'),
                        dataIndex: 'company',
                        dataType : 'string',
                        width    : 100,
                        editable : true,
                        className: 'clickable'
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'street'),
                        dataIndex: 'street_no',
                        dataType : 'string',
                        width    : 100,
                        editable : true,
                        className: 'clickable'
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'zip'),
                        dataIndex: 'zip',
                        dataType : 'string',
                        width    : 100,
                        editable : true,
                        className: 'clickable'
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'city'),
                        dataIndex: 'city',
                        dataType : 'string',
                        width    : 100,
                        editable : true,
                        className: 'clickable'
                    },
                    {
                        header   : QUILocale.get(lgQUIQQER, 'country'),
                        dataIndex: 'country',
                        dataType : 'string',
                        width    : 100
                    }
                ],
                buttons       : [
                    {
                        name     : 'add',
                        text     : QUILocale.get(lgQUIQQER, 'users.user.address.table.btn.add'),
                        textimage: 'fa fa-plus',
                        events   : {
                            onClick: this.$clickCreate
                        }
                    },
                    {
                        type: 'separator'
                    },
                    {
                        name     : 'edit',
                        text     : QUILocale.get(lgQUIQQER, 'users.user.address.table.btn.edit'),
                        textimage: 'fa fa-edit',
                        disabled : true,
                        events   : {
                            onClick: this.$clickEdit
                        }
                    },
                    {
                        name     : 'delete',
                        text     : QUILocale.get(lgQUIQQER, 'users.user.address.table.btn.delete'),
                        textimage: 'fa fa-trash',
                        disabled : true,
                        events   : {
                            onClick: this.$clickDelete
                        }
                    }
                ],
                editable      : true,
                editondblclick: true
            });

            this.$Grid.addEvents({
                onClick       : this.$onGridClick,
                onRefresh     : this.refresh,
                onEditComplete: this.$editComplete,
                onDblClick    : this.$onGridDblClick
            });

            // this.$Grid.setWidth(size.x - 60);
            this.$Grid.disable();

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            const self = this;
            const User = Users.get(this.getAttribute('userId'));
            let Loaded = Promise.resolve(User);

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
            const size = this.$Elm.getSize();

            return Promise.all([
                this.$Grid.setHeight(size.y),
                this.$Grid.setWidth(size.x)
            ]);
        },

        /**
         * Refresh the address list
         */
        refresh: function () {
            const self = this;

            this.$Grid.disable();
            this.$User.$addresses = false;

            return this.$User.getAddressList().then(function (addressList) {
                let mail, phone, phoneText;
                
                const buildPhoneText = function (entry) {
                    if (entry.no === '') {
                        return;
                    }
                    phoneText = phoneText + ' ' + entry.no + ' (' + entry.type + '), ';
                };

                for (let i = 0, len = addressList.length; i < len; i++) {
                    mail = addressList[i].mail;
                    phone = addressList[i].phone;

                    try {
                        mail = JSON.decode(mail);
                        addressList[i].mail = mail.join(', ');
                    } catch (e) {
                    }

                    try {
                        phoneText = '';
                        phone = JSON.decode(phone);
                        phone.forEach(buildPhoneText);

                        addressList[i].phone = phoneText;
                    } catch (e) {

                    }
                }

                self.$Grid.setData({
                    data: addressList
                });

                self.$Grid.enable();
                self.$onGridClick();
            });
        },

        /**
         * Edit an address
         *
         * @param addressId
         */
        editAddress: function (addressId) {
            const self = this;

            this.$Grid.disable();

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/AddressEditWindow'
            ], function (AddressEditWindow) {
                new AddressEditWindow({
                    addressId: addressId,
                    events   : {
                        onClose : function () {
                            self.$Grid.enable();
                        },
                        onSubmit: function () {
                            self.$Grid.enable();
                            self.refresh();
                        }
                    }
                }).open();
            });
        },

        //region button events

        /**
         * event: on grid click
         */
        $onGridClick: function () {
            const buttons = this.$Grid.getButtons(),
                  sels    = this.$Grid.getSelectedIndices();

            buttons.each(function (Btn) {
                if (Btn.getAttribute('name') !== 'add') {
                    Btn.disable();
                }
            });

            if (sels.length === 1) {
                buttons.each(function (Btn) {
                    Btn.enable();
                });

                return;
            }

            if (sels.length > 1) {
                buttons.each(function (Btn) {
                    if (Btn.getAttribute('name') !== 'delete') {
                        Btn.enable();
                    }
                });
            }
        },

        /**
         * event: on dbl click
         *
         * @param event
         */
        $onGridDblClick: function (event) {
            if (event.cell.get('data-index') === 'id' ||
                event.cell.get('data-index') === 'phone' ||
                event.cell.get('data-index') === 'mail' ||
                event.cell.get('data-index') === 'country'
            ) {
                this.editAddress(this.$Grid.getSelectedData()[0].id);
            }
        },

        /**
         * event: edit grid complete
         */
        $editComplete: function (data) {
            const self      = this,
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
                        country   : rowData.country,
                        phone     : JSON.decode(rowData.phone),
                        mails     : JSON.decode(rowData.mail)
                    })
                });
            });
        },

        /**
         * event: on click at delete address
         */
        $clickDelete: function () {
            const self = this;
            const selected = this.$Grid.getSelectedData();
            const ids = selected.map(function (entry) {
                return entry.id;
            });

            require([
                'package/quiqqer/customer/bin/backend/controls/customer/AddressDeleteWindow'
            ], function (AddressDeleteWindow) {
                new AddressDeleteWindow({
                    addressId: ids,
                    events   : {
                        onSubmit: function () {
                            self.refresh();
                        }
                    }
                }).open();
            });
        },

        /**
         * event: on click at create address
         */
        $clickCreate: function () {
            this.$Grid.disable();

            const self = this;

            QUIAjax.post('ajax_users_address_save', function (addressId) {
                self.editAddress(addressId);
                self.refresh();
                self.$Grid.enable();
            }, {
                uid : self.$User.getId(),
                aid : 0,
                data: '[]'
            });
        },

        /**
         * event: on click at edit address
         */
        $clickEdit: function () {
            this.editAddress(this.$Grid.getSelectedData()[0].id);
        }

        //endregion
    });
});
