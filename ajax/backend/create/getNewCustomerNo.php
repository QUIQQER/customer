<?php

use QUI\ERP\Customer\NumberRange;

/**
 * Return new customer id
 *
 * @return integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_create_getNewCustomerNo',
    function () {
        $NumberRange = new NumberRange();
        return $NumberRange->getNextCustomerNo();
    },
    false,
    'Permission::checkAdminUser'
);
