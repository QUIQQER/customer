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
    'package_quiqqer_customer_ajax_backend_OpenItemsList_getUserOpenItems',
    function ($userId) {
        try {
            $OpenItemsList = Handler::getOpenItemsList(QUI::getUsers()->get((int)$userId));
            $items         = [];

            foreach ($OpenItemsList->getItems() as $Item) {
                $documentType      = $Item->getDocumentType();
                $documentTypeTitle = QUI::getLocale()->get(
                    'quiqqer/customer',
                    'OpenItemsList.documentType.'.$documentType
                );

                $items[] = [
                    'documentNo'        => $Item->getDocumentNo(),
                    'documentType'      => $documentType,
                    'documentTypeTitle' => $documentTypeTitle,
                    'date'              => $Item->getDateFormatted(),
                    'dueDate'           => $Item->getDueDateFormatted(),
                    'net'               => $Item->getAmountTotalNetFormatted(),
                    'vat'               => $Item->getAmountTotalVatFormatted(),
                    'gross'             => $Item->getAmountTotalSumFormatted(),
                    'paid'              => $Item->getAmountPaidFormatted(),
                    'open'              => $Item->getAmountOpenFormatted(),
                    'dunningLevel'      => $Item->getDunningLevel()
                ];
            }

            return [
                'data'  => $items,
                'page'  => 1,
                'total' => \count($items)
            ];
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [
                'data'  => [],
                'page'  => 1,
                'total' => 0
            ];
        }
    },
    ['userId'],
    ['Permission::checkAdminUser', Handler::PERMISSION_OPENITEMS_VIEW]
);
