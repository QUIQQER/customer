<?php

/**
 * Add a file to the user download entry
 *
 * @return array
 */

use QUI\ERP\Customer\CustomerFiles;

QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_files_downloadEntry_removeFile',
    function ($file, $customerId) {
        try {
            CustomerFiles::removeFileFromDownloadEntry((int)$customerId, $file);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            throw new QUI\Exception([
                'quiqqer/customer',
                'exception.ajax.backend.general'
            ]);
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/customer',
                'message.ajax.backend.files.downloadEntry.removeFile.success',
                [
                    'file' => $file
                ]
            )
        );
    },
    ['file', 'customerId'],
    'Permission::checkAdminUser'
);
