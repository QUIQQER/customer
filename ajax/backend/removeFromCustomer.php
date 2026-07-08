<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_removeFromCustomer
 */

use QUI\ERP\Customer\Customers;

/**
 *
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_customer_ajax_backend_removeFromCustomer',
    function ($userId) {
        Customers::getInstance()->removeUserFromCustomerGroup($userId);
        return $userId;
    },
    ['userId'],
    'Permission::checkAdminUser'
);
