<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_addToCustomer
 */

use QUI\ERP\Customer\Customers;

/**
 *
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_addToCustomer',
    function ($userId) {
        Customers::getInstance()->addUserToCustomerGroup($userId);
    },
    ['userId'],
    'Permission::checkAdminUser'
);
