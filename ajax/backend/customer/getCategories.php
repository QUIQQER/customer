<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_getCategories
 */

/**
 * Return the customer panel categories
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getCategories',
    function () {
        return QUI\ERP\Customer\CustomerPanel::getInstance()->getPanelCategories();
    },
    false,
    'Permission::checkAdminUser'
);
