<?php

/**
 * Search customer files (used for customer file Select suggest search)
 *
 * @param string $customerId
 * @param string $searchString
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_files_suggestSearch',
    function ($customerId, $searchString) {
        $files        = QUI\ERP\Customer\CustomerFiles::getFileList((int)$customerId);
        $results      = [];
        $searchString = \mb_strtolower($searchString);

        foreach ($files as $file) {
            if (\mb_strpos(\mb_strtolower($file['basename']), $searchString) !== false) {
                $results[] = $file;
            }
        }

        return $results;
    },
    ['customerId', 'searchString'],
    'Permission::checkAdminUser'
);
