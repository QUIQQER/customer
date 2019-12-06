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
    'package_quiqqer_customer_ajax_backend_files_delete',
    function ($files, $customerId) {
        QUI\ERP\Customer\CustomerFiles::deleteFiles(
            $customerId,
            \json_decode($files, true)
        );
    },
    ['files', 'customerId'],
    'Permission::checkAdminUser'
);
