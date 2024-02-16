<?php

/**
 * Search open items list
 *
 * @param array $searchParams - GRID search params
 * @return array
 */

use QUI\Cache\Manager as QUICacheManager;
use QUI\ERP\Customer\OpenItemsList\Handler;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_customer_ajax_backend_OpenItemsList_getUserOpenItems',
    function ($userId, $searchParams, $forceRefresh) {
        try {
            $userId = (int)$userId;
            $cacheName = 'quiqqer/customer/openitems/' . $userId;
            $refresh = true;

            if (empty($forceRefresh)) {
                try {
                    $openItems = QUICacheManager::get($cacheName);
                    $refresh = false;
                } catch (\Exception $Exception) {
                    // nothing - refresh cache
                }
            }

            if ($refresh) {
                $OpenItemsList = Handler::getOpenItemsList(QUI::getUsers()->get($userId));
                $openItems = $OpenItemsList->getItems();

                QUICacheManager::set($cacheName, $openItems);
            }

            $searchParams = Orthos::clearArray(\json_decode($searchParams, true));

            // Filter
            if (!empty($searchParams['search'])) {
                $search = \trim($searchParams['search']);

                $openItems = \array_filter($openItems, function ($Item) use ($search) {
                    return \mb_strpos($Item->getDocumentNo(), $search) !== false;
                });
            }

            // Sort
            if (!empty($searchParams['sortOn'])) {
                $sortOn = $searchParams['sortOn'];
            } else {
                $sortOn = 'date';
            }

            $sortBy = 'DESC';

            if (!empty($searchParams['sortBy'])) {
                switch (\mb_strtoupper($searchParams['sortBy'])) {
                    case 'ASC':
                    case 'DESC':
                        $sortBy = $searchParams['sortBy'];
                        break;
                }
            }

            \usort($openItems, function ($ItemA, $ItemB) use ($sortOn, $sortBy) {
                /**
                 * @var \QUI\ERP\Customer\OpenItemsList\Item $ItemA
                 * @var \QUI\ERP\Customer\OpenItemsList\Item $ItemB
                 */
                switch ($sortOn) {
                    case 'documentNo':
                        $valA = (int)\preg_replace('#[^\d]#i', '', $ItemA->getDocumentNo());
                        $valB = (int)\preg_replace('#[^\d]#i', '', $ItemB->getDocumentNo());
                        break;

                    case 'documentType':
                        $valA = $ItemA->getDocumentType();
                        $valB = $ItemB->getDocumentType();
                        break;

                    case 'dueDate':
                        $valA = $ItemA->getDueDate();
                        $valB = $ItemB->getDueDate();
                        break;

                    case 'net':
                        $valA = $ItemA->getAmountTotalNet();
                        $valB = $ItemB->getAmountTotalNet();
                        break;

                    case 'vat':
                        $valA = $ItemA->getAmountTotalVat();
                        $valB = $ItemB->getAmountTotalVat();
                        break;

                    case 'gross':
                        $valA = $ItemA->getAmountTotalSum();
                        $valB = $ItemB->getAmountTotalSum();
                        break;

                    case 'paid':
                        $valA = $ItemA->getAmountPaid();
                        $valB = $ItemB->getAmountPaid();
                        break;

                    case 'open':
                        $valA = $ItemA->getAmountOpen();
                        $valB = $ItemB->getAmountOpen();
                        break;

                    case 'dunningLevel':
                        $valA = $ItemA->getDunningLevel();
                        $valB = $ItemB->getDunningLevel();
                        break;

                    case 'daysDue':
                        $valA = $ItemA->getDaysDue();
                        $valB = $ItemB->getDaysDue();
                        break;

                    default:
                        $valA = $ItemA->getDate();
                        $valB = $ItemB->getDate();
                }

                if ($valA === $valB) {
                    return 0;
                }

                if ($sortBy === 'ASC') {
                    return $valA < $valB ? -1 : 1;
                } else {
                    return $valA < $valB ? 1 : -1;
                }
            });

            // Pagination
            $page = 1;

            if (!empty($searchParams['page'])) {
                $page = (int)$searchParams['page'];
            }

            $perPage = 10;

            if (!empty($searchParams['perPage'])) {
                $perPage = (int)$searchParams['perPage'];
            }

            $offset = ($page - 1) * $perPage;
            $totalCount = \count($openItems);
            $openItems = \array_splice($openItems, $offset, $perPage);

            // Parse data for GRID display
            $items = [];

            /** @var \QUI\ERP\Customer\OpenItemsList\Item $Item */
            foreach ($openItems as $Item) {
                $documentType = $Item->getDocumentType();
                $documentTypeTitle = QUI::getLocale()->get(
                    'quiqqer/customer',
                    'OpenItemsList.documentType.' . $documentType
                );

                $items[] = [
                    'hash' => $Item->getHash(),
                    'documentId' => $Item->getDocumentId(),
                    'documentNo' => $Item->getDocumentNo(),
                    'documentType' => $documentType,
                    'documentTypeTitle' => $documentTypeTitle,
                    'date' => $Item->getDateFormatted(),
                    'dueDate' => $Item->getDueDateFormatted(),
                    'net' => $Item->getAmountTotalNetFormatted(),
                    'vat' => $Item->getAmountTotalVatFormatted(),
                    'gross' => $Item->getAmountTotalSumFormatted(),
                    'paid' => $Item->getAmountPaidFormatted(),
                    'open' => $Item->getAmountOpenFormatted(),
                    'dunningLevel' => $Item->getDunningLevel() ?: '-',
                    'daysDue' => $Item->getDaysDue(),
                    'daysOpen' => $Item->getDaysOpen()
                ];
            }

            return [
                'data' => $items,
                'page' => $page,
                'total' => $totalCount
            ];
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [
                'data' => [],
                'page' => 1,
                'total' => 0
            ];
        }
    },
    ['userId', 'searchParams', 'forceRefresh'],
    ['Permission::checkAdminUser', Handler::PERMISSION_OPENITEMS_VIEW]
);
