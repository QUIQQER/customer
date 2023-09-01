<?php

/**
 * Return customer ID prefix
 *
 * @return integer
 */

use QUI\ERP\Customer\NumberRange;

QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_create_getPrefix',
    function () {
        $NumberRange = new NumberRange();
        return $NumberRange->getCustomerNoPrefix();
    },
    false,
    'Permission::checkAdminUser'
);
