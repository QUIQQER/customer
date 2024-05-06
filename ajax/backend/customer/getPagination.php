<?php

/**
 * Get pagination control for navigating customer comments / history
 *
 * @param array $attributes
 * @return string - HTML of pagination control
 */

use QUI\Controls\Navigating\Pagination;

QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getPagination',
    function ($uid) {
        try {
            $User = QUI::getUsers()->get($uid);
            $Comments = QUI\ERP\Comments::getCommentsByUser($User);
            $History = QUI\ERP\Comments::getHistoryByUser($User);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return '';
        }

        $comments = array_merge(
            $Comments->toArray(),
            $History->toArray()
        );

        if (count($comments) <= 10) {
            return '';
        }

        $Pagination = new Pagination([
            'count' => count($comments),
            'useAjax' => true,
            'limit' => 10
        ]);

        $Output = new QUI\Output();
        $control = $Pagination->create();
        $css = QUI\Control\Manager::getCSS();

        return $Output->parse($css . $control);
    },
    ['uid'],
    'Permission::checkAdminUser'
);
