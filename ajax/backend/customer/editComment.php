<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_editComment
 */

use QUI\ERP\Customer\Customers;

/**
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_editComment',
    function ($userId, $commentId, $source, $comment) {
        QUI\Permissions\Permission::checkPermission('quiqqer.customer.editComments');

        $User = QUI::getUsers()->get($userId);

        Customers::getInstance()->editComment(
            $User,
            $commentId,
            $source,
            $comment
        );

        return QUI\ERP\Comments::getCommentsByUser($User)->toArray();
    },
    ['userId', 'commentId', 'source', 'comment'],
    'Permission::checkAdminUser'
);
