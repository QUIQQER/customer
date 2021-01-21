<?php

use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;
use QUI\ERP\Customer\OpenItemsList\Handler;

/**
 * Search open items list
 *
 * @param array $searchParams - GRID search params
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_OpenItemsList_search',
    function ($searchParams) {
        try {
            $searchParams = Orthos::clearArray(\json_decode($searchParams, true));
            $result       = Handler::searchOpenItems($searchParams);
            $result       = Handler::parseForGrid($result);

            $searchParams['count'] = true;
            $count                 = Handler::searchOpenItems($searchParams);

            $Grid = new Grid($searchParams);

            if (!empty($searchParams['currency'])) {
                $Currency = new \QUI\ERP\Currency\Currency($searchParams['currency']);
            } else {
                $Currency = \QUI\ERP\Currency\Handler::getDefaultCurrency();
            }

            return [
                'grid'   => $Grid->parseResult($result, $count),
                'totals' => Handler::getTotals($result, $Currency)
            ];
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [
                'grid'   => [],
                'totals' => [
                    'display_gross_toPay' => '',
                    'display_gross_paid'  => '',
                    'display_gross_total' => ''
                ]
            ];
        }
    },
    ['searchParams'],
    ['Permission::checkAdminUser', Handler::PERMISSION_OPENITEMS_VIEW]
);
