<?php

/**
 * This file contains the PDF download for an ERP Output document
 * It opens the native download dialog
 */

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

if (!isset($_REQUEST['file'])
    || !isset($_REQUEST['customerId'])
    || !isset($_REQUEST['extension'])
) {
    exit;
}

require_once dirname(__FILE__, 5).'/header.php';

use QUI\Utils\Security\Orthos;

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

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
                MH.addError("'.htmlspecialchars($message).'");
            });
        });
    }
    </script>';

    exit;
}

$Request    = QUI::getRequest();
$file       = Orthos::clear($Request->query->get('file'));
$extension  = Orthos::clear($Request->query->get('extension'));
$customerId = Orthos::clear($Request->query->get('customerId'));

try {
    $Customer    = QUI::getUsers()->get($customerId);
    $customerDir = QUI\ERP\Customer\CustomerFiles::getFolderPath($Customer);

    $filePath = $customerDir.DIRECTORY_SEPARATOR.$file.'.'.$extension;

    if (\file_exists($filePath)) {
        QUI\Utils\System\File::send($filePath, 0, $file.'.'.$extension);
    }
} catch (\Exception $Exception) {
    QUI\System\Log::addDebug($Exception->getMessage());

    return;
}
