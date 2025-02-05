<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_getBusinessType
 */

/**
 * Return the shop business type
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_getBusinessType',
    function () {
        return QUI\ERP\Utils\Shop::getBusinessType();
    },
    false,
    'Permission::checkAdminUser'
);
