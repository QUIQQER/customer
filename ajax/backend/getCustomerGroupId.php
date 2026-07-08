<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_getCustomerGroupId
 */

/**
 * Return the customer group id
 *
 * @return array
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_customer_ajax_backend_getCustomerGroupId',
    function () {
        try {
            return QUI\ERP\Customer\Customers::getInstance()->getCustomerGroupId();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        return 0;
    },
    false,
    'Permission::checkAdminUser'
);
