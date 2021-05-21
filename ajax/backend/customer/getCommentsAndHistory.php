<?php

/**
 * Return all comments and history entries for an user
 * - considers comments from other ERP modules
 *
 * @param int $page - Pagination page no.
 * @param int $pageSize - Pagination page size
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_customer_getCommentsAndHistory',
    function ($uid, $page, $limit) {
        $User     = QUI::getUsers()->get($uid);
        $Comments = QUI\ERP\Comments::getCommentsByUser($User);
        $History  = QUI\ERP\Comments::getHistoryByUser($User);

        $comments = \array_merge(
            $Comments->toArray(),
            $History->toArray()
        );

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
        $limit  = !empty($limit) ? (int)$limit : 5;
        $offset = ($page - 1) * $limit;

        return \array_slice($comments, $offset, $limit);
    },
    ['uid', 'page', 'limit'],
    'Permission::checkAdminUser'
);
