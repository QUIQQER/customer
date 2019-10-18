/**
 * @module package/quiqqer/customer/bin/backend/controls/CustomerPanel
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/customer/bin/backend/controls/CustomerPanel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'Users'

], function (QUI, QUIPanel, Users) {
    "use strict";

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/customer/bin/backend/controls/CustomerPanel',

        Binds: [
            '$onCreate'
        ],

        initialize: function (parent) {
            this.parent(parent);

            this.addEvents({
                onCreate: this.$onCreate
            });
        },

        $onCreate: function () {

        }
    });
});