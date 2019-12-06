<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_getComments
 */

/**
 * Return all comments for an user
 * - considers comments from invoice
 * - considers comments from orders
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getComments',
    function ($uid) {
        $User     = QUI::getUsers()->get($uid);
        $Comments = QUI\ERP\Comments::getCommentsByUser($User);

        return $Comments->toArray();
    },
    ['uid'],
    'Permission::checkAdminUser'
);
