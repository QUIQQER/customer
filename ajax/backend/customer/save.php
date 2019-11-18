<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_save
 */

use QUI\ERP\Customer\Customers;

/**
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_save',
    function ($userId, $data) {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.edit');

        Customers::getInstance()->setAttributesToCustomer(
            $userId,
            \json_decode($data, true)
        );
    },
    ['userId', 'data'],
    'Permission::checkAdminUser'
);
