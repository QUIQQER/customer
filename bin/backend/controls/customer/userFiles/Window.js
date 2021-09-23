/**
 * Popup that contains the files of a customer (with select option).
 *
 * @module package/quiqqer/customer/bin/backend/controls/customer/userFiles/Search
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSelect [selectedFiles, this]
 */
define('package/quiqqer/customer/bin/backend/controls/customer/userFiles/Window', [

    'qui/controls/windows/Confirm',
    'package/quiqqer/customer/bin/backend/controls/customer/Panel.UserFiles',
    'Locale',

    'css!package/quiqqer/customer/bin/backend/controls/customer/userFiles/Window.css'

], function (QUIConfirm, CustomerFiles, QUILocale) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Window',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            userId: false,

            maxHeight: 600,
            maxWidth : 800,
            icon     : 'fa fa-file-text-o',
            title    : QUILocale.get(lg, 'control.userFiles.window.title'),
            autoclose: false,

            cancel_button: {
                text     : QUILocale.get('quiqqer/system', 'cancel'),
                textimage: 'fa fa-remove'
            },
            ok_button    : {
                text     : QUILocale.get(lg, 'control.userFiles.window.btn.select'),
                textimage: 'fa fa-check'
            }
        },

        initialize: function (options) {
            this.parent(options);

            this.$Search = null;
            this.$Result = null;

            this.$ButtonCancel = null;
            this.$ButtonSubmit = null;

            this.$FileList = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        /**
         * Event: onOpen
         */
        $onOpen: function (Win) {
            var Content = Win.getContent();

            Content.set('html', '');

            this.$FileList = new CustomerFiles({
                selectMode: true,
                userId    : this.getAttribute('userId'),
                events    : {
                    onSelect: () => {
                        this.submit();
                    }
                }
            }).inject(Content);
        },

        /**
         * Event: onSubmit
         */
        $onSubmit: function () {
            if (this.$FileList) {
                this.fireEvent('select', [this.$FileList.getSelectedFiles(), this]);
            }
        }
    });
});
