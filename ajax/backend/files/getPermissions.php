<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_files_getList
 */

use QUI\Permissions\Permission;

/**
 * Return the permissions for file action
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_files_getPermissions',
    function () {
        return [
            'fileEdit' => Permission::hasPermission('quiqqer.customer.fileEdit'),
            'fileView' => Permission::hasPermission('quiqqer.customer.fileView'),
            'fileUpload' => Permission::hasPermission('quiqqer.customer.fileUpload'),
        ];
    },
    false,
    'Permission::checkAdminUser'
);
