<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_addComment
 */

use QUI\ERP\Customer\Customers;

/**
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_addComment',
    function ($userId, $comment) {
        $User = QUI::getUsers()->get($userId);
        Customers::getInstance()->addCommentToUser($User, $comment);

        return QUI\ERP\Comments::getCommentsByUser($User)->toArray();
    },
    ['userId', 'comment'],
    'Permission::checkAdminUser'
);
