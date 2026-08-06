<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/Contract/DemoDataCreatorInterface.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/Contract/DemoDataProviderInterface.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/CreatedDemoData.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/CreatedDemoDataCollection.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/DemoDataCreationContext.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/DemoDataReference.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/DemoDataReferenceCollection.php';
