<?php

namespace QUI\ERP\Customer;

use QUI;

/**
 * Class Utils
 *
 * @package QUI\ERP\Customer
 */
class Utils extends QUI\Utils\Singleton
{
    /**
     * @return array
     */
    public function getCategoriesForCustomerCreate(): array
    {
        $categories = [];

        $categories[] = [
            'text'      => QUI::getLocale()->get('quiqqer/customer', 'customer.create.category.details'),
            'textimage' => 'fa fa-id-card',
            'require'   => ''
        ];

        $categories[] = [
            'text'      => QUI::getLocale()->get('quiqqer/customer', 'customer.create.category.address'),
            'textimage' => 'fa fa-address-book',
            'require'   => ''
        ];

        return $categories;
    }

    /**
     * @param integer $uid
     * @return int
     */
    public function getPaymentTimeForUser(int $uid): int
    {
        $defaultPaymentTime = 0;

        if (class_exists('QUI\ERP\Accounting\Invoice\Settings')) {
            $defaultPaymentTime = (int)QUI\ERP\Accounting\Invoice\Settings::getInstance()
                ->get('invoice', 'time_for_payment');
        }

        try {
            $User = QUI::getUsers()->get($uid);
        } catch (QUI\Exception $Exception) {
            // default time for payment
            return $defaultPaymentTime;
        }

        $permission = $User->getPermission('quiqqer.invoice.timeForPayment', 'maxInteger');

        if (empty($permission)) {
            $permission = $defaultPaymentTime;
        }

        if ($User->getAttribute('quiqqer.erp.customer.payment.term')) {
            $permission = $User->getAttribute('quiqqer.erp.customer.payment.term');
        }

        return $permission;
    }

    /**
     * Return the global customer group
     *
     * @return QUI\Groups\Everyone|QUI\Groups\Group|QUI\Groups\Guest|null
     */
    public function getCustomerGroup()
    {
        $Package = QUI::getPackage('quiqqer/customer');
        $Config  = $Package->getConfig();
        $groupId = $Config->getValue('customer', 'groupId');

        if (empty($groupId)) {
            return null;
        }

        return QUI::getGroups()->get($groupId);
    }
}
