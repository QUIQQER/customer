<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_getCategory
 */

/**
 * Return one customer panel from customer categories
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getCategory',
    function ($category) {
        return QUI\ERP\Customer\CustomerPanel::getInstance()->getPanelCategory($category);
    },
    ['category'],
    'Permission::checkAdminUser'
);
