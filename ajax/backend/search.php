<?php

/**
 * This file contains package_quiqqer_customer_ajax_backend_search
 */

/**
 * Execute the customer search
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_search',
    function ($params) {
        $params = \json_decode($params, true);
        $Search = new QUI\ERP\Customer\Search();

        if (isset($params['filter'])) {
            foreach ($params['filter'] as $filter => $value) {
                $Search->setFilter($filter, $value);
            }
        }

        if (isset($params['search'])) {
            $Search->setFilter('search', $params['search']);
        }

        if (isset($params['onlyCustomer']) && $params['onlyCustomer']) {
            $Search->searchOnlyInCustomer();
        } else {
            $Search->searchInAllGroups();
        }

        // limit
        $start = 0;
        $count = 50;

        if (isset($params['perPage'])) {
            $count = (int)$params['perPage'];
        }

        if (isset($params['page'])) {
            $start = ($params['page'] * $count) - $count;
        }

        $Search->limit($start, $count);


        // exec
        return $Search->searchForGrid();
    },
    ['params'],
    'Permission::checkAdminUser'
);
