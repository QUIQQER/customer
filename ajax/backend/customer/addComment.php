<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_addComment
 */

use QUI\ERP\Customer\Customers;

/**
 *
 * @return array
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_addComment',
    function ($userId, $comment) {
        $User = QUI::getUsers()->get($userId);
        Customers::getInstance()->addCommentToUser($User, $comment);

        $Comments = QUI\ERP\Comments::getCommentsByUser($User);

        if (!$Comments instanceof QUI\ERP\Comments) {
            return [];
        }

        return $Comments->toArray();
    },
    ['userId', 'comment'],
    'Permission::checkAdminUser'
);
