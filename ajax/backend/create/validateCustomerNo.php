<?php

use QUI\ERP\Customer\NumberRange;
use QUI\Utils\Security\Orthos;

/**
 * Validate customer no.
 *
 * @param string|int $customerNo
 * @return integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_create_validateCustomerNo',
    function ($customerNo) {
        $customerNo = Orthos::clear($customerNo);

        try {
            $customerGroupId = \QUI\ERP\Customer\Customers::getInstance()->getCustomerGroupId();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        $sql = "SELECT `id` FROM ".QUI::getUsers()::table();
        $sql .= " WHERE `username` = '$customerNo' OR `customerId` = '$customerNo'";
//        $sql .= " AND `usergroup` LIKE '%,$customerGroupId,%')";
        $sql .= " LIMIT 1";

        $result = QUI::getDataBase()->fetchSQL($sql);

        if (empty($result)) {
            return;
        }

        $NumberRange = new NumberRange();
        $prefix      = $NumberRange->getCustomerNoPrefix();

        throw new \QUI\ERP\Customer\Exception([
            'quiqqer/customer',
            'exception.customer_no_already_exists',
            [
                'customerNo' => $prefix.$customerNo
            ]
        ]);
    },
    ['customerNo'],
    'Permission::checkAdminUser'
);
