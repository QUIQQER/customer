<?php

/**
 * Get details of a customer file by file hash
 *
 * @param int $customerId
 * @param string $fileHash
 * @return array|false - File details or false if file not found
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_files_get',
    function ($customerId, $fileHash) {
        return QUI\ERP\Customer\CustomerFiles::getFileByHash(
            (int)$customerId,
            \QUI\Utils\Security\Orthos::clear($fileHash)
        );
    },
    ['customerId', 'fileHash'],
    'Permission::checkAdminUser'
);
