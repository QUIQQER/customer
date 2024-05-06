<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_addAddress
 */

/**
 * Add a new address to the user
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_addAddress',
    function ($userId) {
        return QUI::getUsers()->get($userId)->addAddress()->getUUID();
    },
    ['userId'],
    'Permission::checkAdminUser'
);
