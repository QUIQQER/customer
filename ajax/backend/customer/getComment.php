<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_getComment
 */

use QUI\ERP\Customer\Customers;

/**
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getComment',
    function ($userId, $commentId, $source) {


        $User     = QUI::getUsers()->get($userId);
        $comments = Customers::getInstance()->getUserComments($User)->toArray();

        foreach ($comments as $comment) {
            if ($comment['id'] === $commentId && $comment['source'] === $source) {
                return $comment['message'];
            }
        }

        return '';
    },
    ['userId', 'commentId', 'source'],
    'Permission::checkAdminUser'
);
