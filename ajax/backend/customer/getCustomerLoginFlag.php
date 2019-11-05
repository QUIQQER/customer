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
    'package_quiqqer_customer_ajax_backend_customer_getCustomerLoginFlag',
    function () {
        return QUI\ERP\Customer\Customers::getInstance()->getCustomerLoginFlag();
    },
    false,
    'Permission::checkAdminUser'
);
