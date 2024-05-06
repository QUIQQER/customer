<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_getCustomersData
 */

/**
 * Return the customer data
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_getCustomersData',
    function ($customerIds) {
        $customerIds = json_decode($customerIds, true);
        $result = [];

        foreach ($customerIds as $customerId) {
            try {
                $User = QUI::getUsers()->get($customerId);

                $result[] = [
                    'id' => $User->getUUID(),
                    'title' => $User->getName(),
                    'username' => $User->getUsername()
                ];
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        return $result;
    },
    ['customerIds'],
    'Permission::checkAdminUser'
);
