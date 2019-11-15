<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_create_getNewCustomerNo
 */

/**
 * Return new customer id
 *
 * @return integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_create_getNewCustomerNo',
    function () {
        $result = QUI::getDataBase()->fetch([
            'from'  => QUI::getUsers()->table(),
            'limit' => 1,
            'order' => 'id DESC'
        ]);

        if (!isset($result[0])) {
            return 1;
        }

        return (int)$result[0]['id'] + 1;
    },
    false,
    'Permission::checkAdminUser'
);
