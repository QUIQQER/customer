<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_addAddress
 */

/**
 * Add a new address to the user
 *
 * @return string
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_addAddress',
    function ($userId) {
        $Address = QUI::getUsers()->get($userId)->addAddress();

        if (!$Address instanceof QUI\Users\Address) {
            throw new QUI\Exception('Could not create address.');
        }

        return $Address->getUUID();
    },
    ['userId'],
    'Permission::checkAdminUser'
);
