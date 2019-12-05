/**
 * Makes a customer input field to a field selection field
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/Select
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onAddItem [ this, id ]
 * @event onChange [ this ]
 */
define('package/quiqqer/customer/bin/backend/controls/customer/Select', [

    'qui/QUI',
    'qui/controls/elements/Select',
    'Locale',
    'Ajax',

    'css!package/quiqqer/customer/bin/backend/controls/customer/Select.css'

], function (QUIControl, QUIElementSelect, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/customer';

    /**
     * @class package/quiqqer/customer/bin/backend/controls/customer/Select
     *
     * @param {Object} options
     * @param {HTMLInputElement} [Input]  - (optional), if no input given, one would be created
     *
     * @memberof! <global>
     */
    return new Class({

        Extends: QUIElementSelect,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/Select',

        Binds: [
            '$onSearchButtonClick',
            'userSearch'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttribute('Search', this.userSearch);
            this.setAttribute('icon', 'fa fa-user-o');
            this.setAttribute('child', 'package/quiqqer/customer/bin/backend/controls/customer/SelectItem');

            this.setAttribute(
                'placeholder',
                QUILocale.get(lg, 'control.users.select.search.field.placeholder')
            );

            this.addEvents({
                onSearchButtonClick: this.$onSearchButtonClick,
                onCreate           : function () {
                    this.getElm().addClass('quiqqer-customer-select');
                }.bind(this)
            });
        },

        /**
         * Execute the search
         *
         * @param {String} value
         * @returns {Promise}
         */
        userSearch: function (value) {
            return new Promise(function (resolve) {
                QUIAjax.get('ajax_usersgroups_search', function (result) {
                    var i, len;

                    var data       = [],
                        userResult = result.users;

                    for (i = 0, len = userResult.length; i < len; i++) {
                        data.push({
                            id   : userResult[i].id,
                            title: userResult[i].username,
                            icon : 'fa fa fa-user-o'
                        });
                    }

                    resolve(data);
                }, {
                    search: value,
                    fields: false,
                    params: JSON.decode({
                        limit: 10
                    })
                });
            });
        },

        /**
         * event : on search click
         *
         * @param {Object} Select
         * @param {Object} Btn
         */
        $onSearchButtonClick: function (Select, Btn) {
            var oldIcon = Btn.getAttribute('icon');

            Btn.setAttribute('icon', 'fa fa-spinner fa-spin');
            Btn.disable();

            require([
                'package/quiqqer/customer/bin/backend/controls/AdministrationWindow'
            ], function (Window) {
                new Window({
                    autoclose     : true,
                    multiple      : this.getAttribute('multiple'),
                    search        : this.getAttribute('search'),
                    searchSettings: this.getAttribute('searchSettings'),
                    events        : {
                        onSubmit: function (Win, userIds) {
                            for (var i = 0, len = userIds.length; i < len; i++) {
                                this.addItem(userIds[i].id);
                            }
                        }.bind(this)
                    }
                }).open();

                Btn.setAttribute('icon', oldIcon);
                Btn.enable();
            }.bind(this));
        }
    });
});
