<?php

/**
 * This file contains the PDF download for an ERP Output document
 * It opens the native download dialog
 */

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

if (!isset($_REQUEST['customerId'])) {
    exit;
}

if (
    !isset($_REQUEST['hash'])
    && (!isset($_REQUEST['file']) || !isset($_REQUEST['extension']))
) {
    exit;
}

require_once dirname(__FILE__, 5) . '/header.php';

use QUI\Utils\Security\Orthos;

$User = QUI::getUserBySession();
$isBackendUser = $User->canUseBackend();

$Request = QUI::getRequest();
$file = Orthos::clear($Request->query->get('file'));
$hash = Orthos::clear($Request->query->get('hash'));
$extension = Orthos::clear($Request->query->get('extension'));
$customerId = $Request->query->get('customerId');

if ($isBackendUser) {
    try {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.fileView');
    } catch (QUI\Permissions\Exception $Exception) {
        $message = $Exception->getMessage();

        echo '
    <script>
    var parent = window.parent;
    
    if (typeof parent.require !== "undefined") {
        parent.require(["qui/QUI"], function(QUI) {
            QUI.getMessageHandler().then(function(MH) {
                MH.addError("' . htmlspecialchars($message) . '");
            });
        });
    }
    </script>';

        exit;
    }
} elseif ($customerId !== $User->getUUID()) {
    exit;
}

try {
    if (!empty($hash)) {
        $file = QUI\ERP\Customer\CustomerFiles::getFileByHash($customerId, $hash);
        $filePath = $file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'];

        if (file_exists($filePath)) {
            QUI\Utils\System\File::send($filePath, 0, $file['basename']);
        }
    } else {
        $Customer = QUI::getUsers()->get($customerId);
        $customerDir = QUI\ERP\Customer\CustomerFiles::getFolderPath($Customer);

        if (!empty($extension) && $extension !== 'false') {
            $file .= '.' . $extension;
        }

        $filePath = $customerDir . DIRECTORY_SEPARATOR . $file;

        if (file_exists($filePath)) {
            QUI\Utils\System\File::send($filePath, 0, $file);
        }
    }
} catch (\Exception $Exception) {
    QUI\System\Log::addDebug($Exception->getMessage());

    return;
}
