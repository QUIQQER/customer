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
        $files  = QUI\ERP\Customer\CustomerFiles::getFileList((int)$customerId);
        $Locale = QUI::getLocale();

        foreach ($files as $k => $file) {
            $file['uploadTime'] = $Locale->formatDate($file['uploadTime']);

            $files[$k] = $file;
        }

        return $files;
    },
    ['customerId'],
    'Permission::checkAdminUser'
);
