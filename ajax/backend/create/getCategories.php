<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_create_getCategories
 */

/**
 * Return the categories for the customer creation control
 *
 * @return array
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_create_getCategories',
    function () {
        return QUI\ERP\Customer\Utils::getInstance()->getCategoriesForCustomerCreate();
    },
    false,
    'Permission::checkAdminUser'
);
