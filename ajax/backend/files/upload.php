<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_files_upload
 */

use QUI\Permissions\Permission;

/**
 * Upload finish event
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_files_upload',
    function ($File, $customerId) {
        if (!Permission::hasPermission('quiqqer.customer.fileUpload')) {
            return false;
        }

        if (!($File instanceof QUI\QDOM)) {
            return false;
        }

        $file = $File->getAttribute('filepath');

        if (!file_exists($file)) {
            return false;
        }

        return QUI\ERP\Customer\CustomerFiles::addFileToCustomer($customerId, $file);
    },
    ['File', 'customerId'],
    'Permission::checkAdminUser'
);
