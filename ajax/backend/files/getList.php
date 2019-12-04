<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_files_getList
 */

/**
 * Return the file list of a customer
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_files_getList',
    function ($customerId) {
        return QUI\ERP\Customer\CustomerFiles::getFileList($customerId);
    },
    ['customerId'],
    'Permission::checkAdminUser'
);
