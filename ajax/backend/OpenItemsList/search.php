<?php

/**
 * Search open items list
 *
 * @param array $searchParams - GRID search params
 * @return array
 */

use QUI\ERP\Customer\OpenItemsList\Handler;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;

QUI::getAjax()->registerFunction(
    'package_quiqqer_customer_ajax_backend_OpenItemsList_search',
    function ($searchParams) {
        try {
            $searchParams = Orthos::clearArray(json_decode($searchParams, true));

            $result = Handler::searchOpenItems($searchParams);

            if (!is_array($result)) {
                $result = [];
            }

            $result = Handler::parseForGrid($result);

            $searchParams['count'] = true;
            $count = Handler::searchOpenItems($searchParams);

            if (!is_int($count)) {
                $count = 0;
            }

            $Grid = new Grid($searchParams);

            if (!empty($searchParams['currency'])) {
                $Currency = \QUI\ERP\Currency\Handler::getCurrency($searchParams['currency']);
            } else {
                $Currency = \QUI\ERP\Currency\Handler::getDefaultCurrency();
            }

            if (!$Currency instanceof \QUI\ERP\Currency\Currency) {
                throw new QUI\Exception('Could not determine currency.');
            }

            return [
                'grid' => $Grid->parseResult($result, $count),
                'totals' => Handler::getTotals($result, $Currency)
            ];
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [
                'grid' => [],
                'totals' => [
                    'display_net' => 0,
                    'display_vat' => 0,
                    'display_gross' => 0,
                    'display_paid' => 0,
                    'display_open' => 0
                ]
            ];
        }
    },
    ['searchParams'],
    ['Permission::checkAdminUser', Handler::PERMISSION_OPENITEMS_VIEW]
);
