<?php

namespace QUI\ERP\Customer\OpenItemsList;

use DateTime;
use Exception;
use QUI;
use QUI\ERP\Accounting\Dunning\Handler as DunningsHandler;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Order\ProcessingStatus\Handler as OrderStatusHandler;
use QUI\Utils\Grid;

use function array_column;
use function array_sum;
use function array_values;
use function count;
use function date_create;
use function implode;
use function in_array;
use function is_scalar;
use function json_decode;
use function usort;

/**
 * Class Handler
 *
 * Generates open items lists for customers
 */
class Handler
{
    /**
     * Tables
     */
    const TBL_OPEN_ITEMS = 'customer_open_items';

    /**
     * Permissions
     */
    const PERMISSION_OPENITEMS_VIEW = 'quiqqer.customer.OpenItemsList.view';

    const DOCUMENT_TYPE_INVOICE = 'invoice';
    const DOCUMENT_TYPE_ORDER = 'order';

    protected static function createDateTime(mixed $value = 'now'): DateTime
    {
        if (!is_scalar($value)) {
            return new DateTime();
        }

        $Date = date_create((string)$value);

        if (!$Date instanceof DateTime) {
            return new DateTime();
        }

        return $Date;
    }

    /**
     * @param array<string, mixed> $searchParams
     * @return array{column: string, direction: 'ASC'|'DESC'}
     */
    protected static function resolveOpenItemsSort(array $searchParams): array
    {
        $sortColumns = [
            'userId' => 'userId',
            'customerId' => 'customerId',
            'net_sum' => 'net_sum',
            'vat_sum' => 'vat_sum',
            'total_sum' => 'total_sum',
            'paid_sum' => 'paid_sum',
            'open_sum' => 'open_sum',
            'currency' => 'currency',
            'open_items_count' => 'open_items_count',
            'display_net_sum' => 'net_sum',
            'display_vat_sum' => 'vat_sum',
            'display_total_sum' => 'total_sum',
            'display_paid_sum' => 'paid_sum',
            'display_open_sum' => 'open_sum',
            'currency_code' => 'currency'
        ];
        $requestedSort = isset($searchParams['sortOn']) && is_string($searchParams['sortOn'])
            ? $searchParams['sortOn']
            : 'userId';
        $sortBy = empty($searchParams['sortOn']) ? 'DESC' : 'ASC';

        if (!empty($searchParams['sortBy']) && strtoupper((string)$searchParams['sortBy']) === 'DESC') {
            $sortBy = 'DESC';
        }

        return [
            'column' => $sortColumns[$requestedSort] ?? 'userId',
            'direction' => $sortBy
        ];
    }

    /**
     * Generate an open items list for a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return ItemsList
     * @throws QUI\Exception
     */
    public static function getOpenItemsList(QUI\Interfaces\Users\User $User): ItemsList
    {
        $List = new ItemsList();
        $List->setDate(self::createDateTime());
//        $List->setUser($User);

        // Fetch open invoices
        $invoices = self::getOpenInvoices($User);
        $addedGlobalProcessIds = [];

        foreach ($invoices as $Invoice) {
            $InvoiceItem = self::parseInvoiceToOpenItem($Invoice);
            $List->addItem($InvoiceItem);

            $addedGlobalProcessIds[] = $Invoice->getGlobalProcessId();
        }

        try {
            $considerOrders = QUI::getPackage('quiqqer/customer')
                ->getConfig()
                ?->get('openItems', 'considerOrders');

            if (!empty($considerOrders)) {
                $orders = self::getOpenOrders($User);

                foreach ($orders as $Order) {
                    $OrderItem = self::parseOrderToOpenItem($Order);

                    // Only add an order if there is no invoice with an identical global process id
                    if (
                        !in_array($Order->getGlobalProcessId(), $addedGlobalProcessIds)/* &&
                        !self::isOrderLinkedToInvoiceEligibleAsOpenItem($OrderItem)*/
                    ) {
                        $List->addItem($OrderItem);
                    }
                }
            }
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        // @todo Fetch open dunnings

        return $List;
    }

    // region Invoices

    /**
     * Get all open invoices of a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return array<Invoice|InvoiceTemporary>
     * @throws QUI\Database\Exception
     */
    protected static function getOpenInvoices(QUI\Interfaces\Users\User $User): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/invoice')) {
            return [];
        }

        $Invoices = InvoiceHandler::getInstance();

        $QueryBuilder = QUI::getQueryBuilder();
        $result = $QueryBuilder
            ->select(QUI\Utils\Doctrine::quoteIdentifier('id'))
            ->from(QUI\Utils\Doctrine::quoteIdentifier($Invoices->invoiceTable()))
            ->where($QueryBuilder->expr()->notIn(
                QUI\Utils\Doctrine::quoteIdentifier('paid_status'),
                ':excludedStatuses'
            ))
            ->andWhere($QueryBuilder->expr()->eq(
                QUI\Utils\Doctrine::quoteIdentifier('customer_id'),
                ':customerId'
            ))
            ->andWhere($QueryBuilder->expr()->eq(QUI\Utils\Doctrine::quoteIdentifier('type'), ':type'))
            ->setParameter('excludedStatuses', [
                QUI\ERP\Constants::PAYMENT_STATUS_PAID,
                QUI\ERP\Constants::PAYMENT_STATUS_CANCELED,
                QUI\ERP\Constants::PAYMENT_STATUS_DEBIT
            ], \Doctrine\DBAL\ArrayParameterType::INTEGER)
            ->setParameter('customerId', $User->getUUID())
            ->setParameter('type', QUI\ERP\Constants::TYPE_INVOICE)
            ->executeQuery()
            ->fetchAllAssociative();

        $invoices = [];

        foreach ($result as $row) {
            try {
                $Invoice = $Invoices->get($row['id']);

                // Filter invoices that are considered paid (even if not in database)
                if ($Invoice->isPaid()) {
                    continue;
                }

                $invoices[] = $Invoice;
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $invoices;
    }

    /**
     * Parses invoice data to an open item
     *
     * @param Invoice|InvoiceTemporary $Invoice
     * @return Item
     * @throws QUI\Exception
     */
    protected static function parseInvoiceToOpenItem(Invoice|InvoiceTemporary $Invoice): Item
    {
        $Item = new Item($Invoice->getId(), self::DOCUMENT_TYPE_INVOICE);

        // Basic data
        $Item->setDocumentNo($Invoice->getPrefixedNumber());
        $Item->setDate(self::createDateTime($Invoice->getAttribute('c_date')));
        $Item->setDueDate(self::createDateTime($Invoice->getAttribute('time_for_payment')));
        $Item->setGlobalProcessId($Invoice->getGlobalProcessId());
        $Item->setHash($Invoice->getUUID());

        // Invoice amounts
        $paidStatus = $Invoice->getPaidStatusInformation();
        $Item->setAmountPaid($paidStatus['paid']);
        $Item->setAmountOpen($paidStatus['toPay']);
        $Item->setAmountTotalNet($Invoice->getAttribute('nettosum'));
        $Item->setAmountTotalSum($Invoice->getAttribute('sum'));
//        $Item->setAmountTotal($Invoice->getAttribute('sum'));

        // VAT
        $vat = json_decode($Invoice->getAttribute('vat_array'), true);
        $vatSum = 0;

        foreach ($vat as $vatEntry) {
            $vatSum += $vatEntry['sum'];
        }

        $Item->setAmountTotalVat($vatSum);
        $Item->setCurrency($Invoice->getCurrency());

        // Latest transaction date
        if ($Invoice instanceof InvoiceTemporary) {
            $transactions = [];
        } else {
            $transactions = InvoiceUtils::getTransactionsByInvoice($Invoice);
        }

        if (!empty($transactions)) {
            // Sort by date
            usort($transactions, function ($TransactionA, $TransactionB) {
                /**
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionA
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionB
                 */
                $DateA = self::createDateTime($TransactionA->getDate());
                $DateB = self::createDateTime($TransactionB->getDate());

                if ($DateA === $DateB) {
                    return 0;
                }

                return $DateA > $DateB ? -1 : 1;
            });

            $LatestTransactionDate = self::createDateTime($transactions[0]->getDate());
            $Item->setLastPaymentDate($LatestTransactionDate);
        }

        // Days due
        $Now = self::createDateTime();
        $TimeForPayment = self::createDateTime($Invoice->getAttribute('time_for_payment'));

        if ($Now < $TimeForPayment) {
            $Item->setDaysDue(0);
        } else {
            $Item->setDaysDue($TimeForPayment->diff($Now)->days + 1);
        }

        // Check if dunning exist
        if (QUI::getPackageManager()->isInstalled('quiqqer/dunning')) {
            $DunningProcess = DunningsHandler::getInstance()->getDunningProcessByInvoiceId($Invoice->getId());

            if ($DunningProcess && $DunningProcess->getCurrentDunning()) {
                $Item->setDunningLevel($DunningProcess->getCurrentDunning()->getDunningLevel()->getLevel());
            }
        }

        return $Item;
    }

    // endregion

    // region Orders

    /**
     * Get all open orders of a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return QUI\ERP\Order\Order[]
     * @throws QUI\Database\Exception
     */
    protected static function getOpenOrders(QUI\Interfaces\Users\User $User): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/order')) {
            return [];
        }

        $where = [
            'paid_status' => [
                'type' => 'NOT IN',
                'value' => [
                    QUI\ERP\Constants::PAYMENT_STATUS_PAID,
                    QUI\ERP\Constants::PAYMENT_STATUS_CANCELED,
                    QUI\ERP\Constants::PAYMENT_STATUS_DEBIT,
                    QUI\ERP\Constants::PAYMENT_STATUS_PLAN
                ]
            ],
            'customerId' => $User->getUUID(),
            'invoice_id' => null
        ];

        $Orders = OrderHandler::getInstance();
        $OrderStatusHandler = new OrderStatusHandler();
        $cancelledStatusId = null;

        try {
            $CancelledStatus = $OrderStatusHandler->getCancelledStatus();

            if ($CancelledStatus->getId()) {
                $cancelledStatusId = $CancelledStatus->getId();
            }
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        $QueryBuilder = QUI::getQueryBuilder();
        $QueryBuilder
            ->select(QUI\Utils\Doctrine::quoteIdentifier('id'))
            ->from(QUI\Utils\Doctrine::quoteIdentifier($Orders->table()))
            ->where($QueryBuilder->expr()->notIn(
                QUI\Utils\Doctrine::quoteIdentifier('paid_status'),
                ':excludedStatuses'
            ))
            ->andWhere($QueryBuilder->expr()->eq(
                QUI\Utils\Doctrine::quoteIdentifier('customerId'),
                ':customerId'
            ))
            ->andWhere($QueryBuilder->expr()->isNull(QUI\Utils\Doctrine::quoteIdentifier('invoice_id')))
            ->setParameter('excludedStatuses', $where['paid_status']['value'], \Doctrine\DBAL\ArrayParameterType::INTEGER)
            ->setParameter('customerId', $User->getUUID());

        if ($cancelledStatusId !== null) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->neq(
                QUI\Utils\Doctrine::quoteIdentifier('status'),
                ':cancelledStatus'
            ));
            $QueryBuilder->setParameter('cancelledStatus', $cancelledStatusId);
        }

        $result = $QueryBuilder->executeQuery()->fetchAllAssociative();

        $orders = [];

        foreach ($result as $row) {
            try {
                $orders[] = $Orders->get($row['id']);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $orders;
    }

    /**
     * Parses order data to an open item
     *
     * @param QUI\ERP\Order\Order $Order
     * @return Item
     *
     * @throws QUI\Exception
     */
    protected static function parseOrderToOpenItem(QUI\ERP\Order\Order $Order): Item
    {
        $Item = new Item($Order->getCleanId(), self::DOCUMENT_TYPE_ORDER);

        // Basic data
        $Item->setDocumentNo($Order->getPrefixedNumber());
        $Item->setDate(self::createDateTime($Order->getAttribute('c_date')));
        $Item->setGlobalProcessId($Order->getGlobalProcessId());
        $Item->setHash($Order->getUUID());

        if (!empty($Order->getAttribute('payment_time'))) {
            $Item->setDueDate(self::createDateTime($Order->getAttribute('payment_time')));
        }

        // Invoice amounts
        $paidStatus = $Order->getPaidStatusInformation();
        $Item->setAmountPaid($paidStatus['paid']);
        $Item->setAmountOpen($paidStatus['toPay']);

        $OrderArticles = $Order->getArticles();
        $calculations = $OrderArticles->getCalculations();

        $Item->setAmountTotalNet($calculations['nettoSum']);
        $Item->setAmountTotalSum($calculations['sum']);

        if (!empty($calculations['vatArray'])) {
            $Item->setAmountTotalVat(array_sum(array_column($calculations['vatArray'], 'sum')));
        }

        $Item->setCurrency($Order->getCurrency());

        // Latest transaction date
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($Order->getUUID());

        if (!empty($transactions)) {
            // Sort by date
            usort($transactions, function ($TransactionA, $TransactionB) {
                /**
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionA
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionB
                 */
                $DateA = self::createDateTime($TransactionA->getDate());
                $DateB = self::createDateTime($TransactionB->getDate());

                if ($DateA === $DateB) {
                    return 0;
                }

                return $DateA > $DateB ? -1 : 1;
            });

            $LatestTransactionDate = self::createDateTime($transactions[0]->getDate());
            $Item->setLastPaymentDate($LatestTransactionDate);
        }

        // Days due
//        $Now            = \date_create();
//        $TimeForPayment = \date_create($Invoice->getAttribute('time_for_payment'));
//        $Item->setDaysDue($TimeForPayment->diff($Now)->days + 1);

        // Check if dunning exist
//        if (QUI::getPackageManager()->isInstalled('quiqqer/dunning')) {
//            $DunningProcess = DunningsHandler::getInstance()->getDunningProcessByInvoiceId($Invoice->getCleanId());
//
//            if ($DunningProcess && $DunningProcess->getCurrentDunning()) {
//                $Item->setDunningLevel($DunningProcess->getCurrentDunning()->getDunningLevel()->getLevel());
//            }
//        }

        return $Item;
    }

    // endregion

    /**
     * Updates the open items record of a user with up-to-date item data.
     *
     * @param QUI\Interfaces\Users\User $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function updateOpenItemsRecord(QUI\Interfaces\Users\User $User): void
    {
        self::syncOpenItemsRecord($User);
    }

    /**
     * Synchronize the persisted open-items snapshot of a user with the current live data.
     *
     * @param QUI\Interfaces\Users\User $User
     * @param ItemsList|null $OpenItemsList
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function syncOpenItemsRecord(
        QUI\Interfaces\Users\User $User,
        ?ItemsList $OpenItemsList = null
    ): void {
        if ($OpenItemsList === null) {
            $OpenItemsList = self::getOpenItemsList($User);
        }

        $items = $OpenItemsList->getItems();

        // If no open items exist -> Delete entry from db
        if (empty($items)) {
            QUI::getDataBaseConnection()->delete(
                QUI\Utils\Doctrine::quoteIdentifier(self::getTable()),
                [
                    'userId' => $User->getUUID()
                ]
            );

            return;
        }

        $customerId = $User->getAttribute('customerId');

        if ($User instanceof QUI\ERP\User) {
            $customerId = $User->getCustomerNo();
        }

        // Parse open items to db entry
        foreach ($OpenItemsList->getTotalAmountsByCurrency() as $currency => $values) {
            $data = [
                'userId' => $User->getUUID(),
                'customerId' => $customerId,
                'net_sum' => $values['netTotal'],
                'total_sum' => $values['sumTotal'],
                'open_sum' => $values['dueTotal'],
                'paid_sum' => $values['paidTotal'],
                'vat_sum' => $values['vatTotal'],
                'open_items_count' => count($OpenItemsList->getItemsByCurrencyCode($currency)),
                'currency' => $currency
            ];
            $criteria = [
                'userId' => $User->getUUID(),
                'currency' => $currency
            ];
            $Connection = QUI::getDataBaseConnection();
            $table = QUI\Utils\Doctrine::quoteIdentifier(self::getTable());

            if ($Connection->update($table, $data, $criteria) === 0) {
                $Connection->insert($table, $data);
            }
        }

        // Clear cache used in backend administration
        QUI\Cache\Manager::clear('quiqqer/customer/openitems/' . $User->getUUID());
    }

    /**
     * Search open items records
     *
     * @param array<string, mixed> $searchParams
     * @return list<array<string, mixed>>|int
     */
    public static function searchOpenItems(array $searchParams): int|array
    {
        $Grid = new Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);
        $countOnly = !empty($searchParams['count']);
        $QueryBuilder = QUI::getQueryBuilder();
        $quote = static fn(string $column): string => QUI\Utils\Doctrine::quoteIdentifier($column);

        if ($countOnly) {
            $QueryBuilder->select('COUNT(*)');
        } else {
            $QueryBuilder->select('*');
        }

        $QueryBuilder->from($quote(self::getTable()));

        if (!empty($searchParams['search'])) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->or(
                $QueryBuilder->expr()->like($quote('customerId'), ':search'),
                $QueryBuilder->expr()->like($quote('userId'), ':search')
            ));
            $QueryBuilder->setParameter('search', '%' . $searchParams['search'] . '%');
        }

        if (!empty($searchParams['currency'])) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->eq($quote('currency'), ':currency'));
            $QueryBuilder->setParameter('currency', $searchParams['currency']);
        }

        if (!empty($searchParams['userId'])) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->eq($quote('userId'), ':userId'));
            $QueryBuilder->setParameter('userId', $searchParams['userId']);
        }

        $sort = self::resolveOpenItemsSort($searchParams);

        if (!$countOnly) {
            $QueryBuilder->orderBy($quote($sort['column']), $sort['direction']);
        }

        if (!$countOnly) {
            if (!empty($gridParams['limit'])) {
                $limit = explode(',', (string)$gridParams['limit'], 2);

                if (isset($limit[1])) {
                    $QueryBuilder->setFirstResult((int)$limit[0]);
                    $QueryBuilder->setMaxResults((int)$limit[1]);
                } else {
                    $QueryBuilder->setMaxResults((int)$limit[0]);
                }
            } else {
                $QueryBuilder->setMaxResults(20);
            }
        }

        try {
            $Result = $QueryBuilder->executeQuery();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        if ($countOnly) {
            return (int)$Result->fetchOne();
        }

        return $Result->fetchAllAssociative();
    }

    /**
     * Parses a search result for display in backend GRID
     *
     * @param array<int, array<string, mixed>> $result
     * @return array<int, array<string, mixed>>
     *
     * @throws QUI\Exception
     */
    public static function parseForGrid(array $result): array
    {
        foreach ($result as $k => $row) {
            $Currency = QUI\ERP\Currency\Handler::getCurrency($row['currency']);

            $sumColumns = [
                'net_sum',
                'vat_sum',
                'total_sum',
                'paid_sum',
                'open_sum'
            ];

            foreach ($sumColumns as $column) {
                $row['display_' . $column] = $Currency->format($row[$column]);
            }

            try {
                $Customer = QUI::getUsers()->get($row['userId']);
                $Customer = QUI\ERP\User::convertUserToErpUser($Customer);

                $row['customer_name'] = $Customer->getInvoiceName();
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                $row['customer_name'] = '-';
            }

            $result[$k] = $row;
        }

        return $result;
    }

    /**
     * Calculate the totals for a set of customer open items
     *
     * @param array<int, array<string, mixed>> $entries - Database rows form customer_open_items table
     * @param QUI\ERP\Currency\Currency $Currency
     * @return array<string, string> - Totals prepared for backend display
     */
    public static function getTotals(array $entries, QUI\ERP\Currency\Currency $Currency): array
    {
        $net = 0;
        $vat = 0;
        $gross = 0;
        $paid = 0;
        $open = 0;

        foreach ($entries as $entry) {
            $net += $entry['net_sum'];
            $vat += $entry['vat_sum'];
            $gross += $entry['total_sum'];
            $paid += $entry['paid_sum'];
            $open += $entry['open_sum'];
        }

        return [
            'display_net' => $Currency->format($net),
            'display_vat' => $Currency->format($vat),
            'display_gross' => $Currency->format($gross),
            'display_paid' => $Currency->format($paid),
            'display_open' => $Currency->format($open)
        ];
    }

    /**
     * Get table that contains open items
     *
     * @return string
     */
    public static function getTable(): string
    {
        return QUI::getDBTableName(self::TBL_OPEN_ITEMS);
    }
}
