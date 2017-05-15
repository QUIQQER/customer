<?php

/**
 * This file contains QUI\ERP\Customer\EventHandler
 */

namespace QUI\ERP\Customer;

use QUI;
use QUI\Package\Package;

/**
 * Class EventHandler
 *
 * @package QUI\ERP\Customer
 */
class EventHandler
{
    /**
     * event: on package setup
     * - create customer group
     *
     * @param Package $Package
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() != 'quiqqer/customer') {
            return;
        }

        // create customer group
        $Config  = $Package->getConfig();
        $groupId = $Config->getValue('customer', 'groupId');

        if (!empty($groupId)) {
            return;
        }

        $Root = QUI::getGroups()->firstChild();

        $Customer = $Root->createChild(
            QUI::getLocale()->get('quiqqer/customer', 'customer.group.name'),
            QUI::getUsers()->getSystemUser()
        );

        $Config->setValue('customer', 'groupId', $Customer->getId());
        $Config->save();
    }

    /**
     * event : on admin header loaded
     */
    public static function onAdminLoadFooter()
    {
        if (!defined('ADMIN') || !ADMIN) {
            return;
        }

        $Package = QUI::getPackageManager()->getInstalledPackage('quiqqer/customer');
        $Config  = $Package->getConfig();
        $groupId = $Config->getValue('customer', 'groupId');

        echo '<script>var QUIQQER_CUSTOMER_GROUP = ' . $groupId . '</script>';
    }
}
