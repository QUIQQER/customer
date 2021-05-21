<?php

use QUI\Utils\Security\Orthos;
use QUI\Controls\Navigating\Pagination;

/**
 * Get pagination control for navigating customer comments / history
 *
 * @param array $attributes
 * @return string - HTML of pagination control
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getPagination',
    function ($uid) {
        $User     = QUI::getUsers()->get((int)$uid);
        $Comments = QUI\ERP\Comments::getCommentsByUser($User);
        $History  = QUI\ERP\Comments::getHistoryByUser($User);

        $comments = \array_merge(
            $Comments->toArray(),
            $History->toArray()
        );

        $Pagination = new Pagination([
            'count'   => \count($comments),
            'useAjax' => true,
            'limit'   => 5
        ]);

        $Output  = new QUI\Output();
        $control = $Pagination->create();
        $css     = QUI\Control\Manager::getCSS();

        return $Output->parse($css.$control);
    },
    ['uid'],
    'Permission::checkAdminUser'
);
