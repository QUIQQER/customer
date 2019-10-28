/**
 * @module package/quiqqer/customer/bin/backend/controls/users/UserGroupsWindow
 */
define('package/quiqqer/customer/bin/backend/controls/users/UserGroupsWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'controls/groups/Select',
    'Users',
    'Locale'

], function (QUI, QUIConfirm, GroupsSelect, Users, QUILocale) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/customer/bin/backend/controls/users/UserGroupsWindow',

        options: {
            icon     : 'fa fa-users',
            maxHeight: 400,
            maxWidth : 600,
            userId   : false
        },

        initialize: function (parent) {
            this.parent(parent);

            this.$Select = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this,
                User = Users.get(this.getAttribute('userId'));

            this.getContent().set('html', '');
            this.Loader.show();

            var LoadUser = Promise.resolve(User);

            if (!User.isLoaded()) {
                LoadUser = User.load();
            }

            LoadUser.then(function (User) {
                self.setAttribute('title', QUILocale.get(lg, 'user.group.window.title', {
                    username: User.getUsername()
                }));

                self.refresh();

                // group listing
                self.$Select = new GroupsSelect({
                    styles: {
                        height: self.getContent().getSize().y - 40
                    }
                }).inject(self.getContent());

                var groups = User.getAttribute('usergroup');

                if (typeOf(groups) !== 'array') {
                    groups = groups.split(',');
                }

                for (var i = 0, len = groups.length; i < len; i++) {
                    self.$Select.addItem(groups[i]);
                }


                self.Loader.hide();
            });
        },

        /**
         * submit the window and saves the groups to the user
         */
        submit: function () {
            if (!this.$Select) {
                return;
            }

            this.Loader.show();

            var self   = this,
                User   = Users.get(this.getAttribute('userId')),
                groups = this.$Select.getValue();

            User.setAttribute('usergroup', groups);

            User.save().then(function () {
                self.fireEvent('submit', [self]);

                if (self.getAttribute('autoclose')) {
                    self.close();
                }
            });
        }
    });
});