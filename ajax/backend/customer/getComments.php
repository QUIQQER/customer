<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_customer_getComments
 */

/**
 * Return all comments for an user
 * - considers comments from invoice
 * - considers comments from orders
 *
 * @param int $page - Pagination page no.
 * @param int $pageSize - Pagination page size
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getComments',
    function ($uid, $page, $limit) {
        $User     = QUI::getUsers()->get($uid);
        $Comments = QUI\ERP\Comments::getCommentsByUser($User);
        $comments = $Comments->toArray();

        // Sorty by time DESC
        \usort($comments, function ($commA, $commB) {
            return $commB['time'] - $commA['time'];
        });

        // nl2br
        \array_walk($comments, function (&$comment) {
            $comment['message'] = \nl2br($comment['message']);
        });

        if (empty($page) && empty($limit)) {
            return $comments;
        }

        $page   = !empty($page) ? (int)$page : 1;
        $limit  = !empty($limit) ? (int)$limit : 10;
        $offset = ($page - 1) * $limit;

        return \array_slice($comments, $offset, $limit);
    },
    ['uid', 'page', 'limit'],
    'Permission::checkAdminUser'
);
