/**
 * @module package/quiqqer/customer/bin/backend/controls/AdministrationPanel
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/AdministrationPanel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'package/quiqqer/customer/bin/backend/controls/Administration',
    'Locale'

], function (QUI, QUIPanel, Administration, QUILocale) {
    "use strict";

    var lg = 'quiqqer/customer';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/customer/bin/backend/controls/AdministrationPanel',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get(lg, 'panel.title'),
                icon : 'fa fa-user-o',
                name : 'customer-administration'
            });

            this.addEvents({
                onInject : this.$onInject,
                onResize : function () {
                    if (this.$Administration) {
                        this.$Administration.resize();
                    }

                    this.Loader.hide();
                }.bind(this),
                onDestroy: function () {
                    if (this.$Administration) {
                        this.$Administration.destroy();
                    }
                }.bind(this)
            });
        },

        /**
         * Create the DOMNode element
         */
        $onInject: function () {
            var self = this;

            this.Loader.show();
            this.getContent().set('html', '');
            this.getContent().setStyle('padding', 0);

            this.$Administration = new Administration({
                events: {
                    onRefreshBegin: function () {
                        self.Loader.show();
                    },

                    onRefreshEnd: function () {
                        self.Loader.hide();
                    }
                }
            });
            this.$Administration.inject(this.getContent());
            this.$Administration.resize();
        }
    });
});
