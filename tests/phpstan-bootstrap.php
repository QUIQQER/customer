<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Dunning/DunningLevel.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Dunning/CurrentDunning.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Dunning/DunningProcess.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Dunning/Handler.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Invoice/Invoice.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Invoice/InvoiceTemporary.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Invoice/Handler.php';
require_once __DIR__ . '/stubs/QUI/ERP/Accounting/Invoice/Utils/Invoice.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/AbstractOrder.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/Order.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/Handler.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/Settings.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/ProcessingStatus/Status.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/ProcessingStatus/Handler.php';
require_once __DIR__ . '/stubs/QUI/ERP/Order/Controls/OrderProcess/CustomerData.php';
require_once __DIR__ . '/stubs/QUI/UserDownloads/Exception.php';
require_once __DIR__ . '/stubs/QUI/UserDownloads/DownloadEntry.php';
require_once __DIR__ . '/stubs/QUI/UserDownloads/Handler.php';
