<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI;
use QUI\ERP\Accounting\Dunning\Handler as DunningsHandler;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;
use QUI\ERP\Order\ProcessingStatus\Handler as OrderStatusHandler;

use function in_array;

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
    const PERMISSION_OPENITEMS_ADD_PAYMENT = 'quiqqer.customer.OpenItemsList.addPayment';

    const DOCUMENT_TYPE_INVOICE = 'invoice';
    const DOCUMENT_TYPE_ORDER = 'order';

    /**
     * Generate an open items list for a user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return ItemsList
     */
    public static function getOpenItemsList(QUI\Interfaces\Users\User $User)
    {
        $List = new ItemsList();

        $List->setDate(\date_create());
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
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $considerOrders = $Conf->get('openItems', 'considerOrders');

            if (!empty($considerOrders)) {
                $orders = self::getOpenOrders($User);

                foreach ($orders as $Order) {
                    $OrderItem = self::parseOrderToOpenItem($Order);

                    // Only add an order if there is no invoice with an identical global process id
                    if (!in_array($Order->getGlobalProcessId(), $addedGlobalProcessIds)) {
                        $List->addItem($OrderItem);
                    }
                }
            }
        } catch (\Exception $Exception) {
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
     * @return Invoice[]
     */
    protected static function getOpenInvoices(QUI\Interfaces\Users\User $User)
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/invoice')) {
            return [];
        }

        $Invoices = InvoiceHandler::getInstance();

        $result = QUI::getDataBase()->fetch([
            'select' => ['id'],
            'from' => $Invoices->invoiceTable(),
            'where' => [
                'paid_status' => [
                    'type' => 'NOT IN',
                    'value' => [
                        QUI\ERP\Constants::PAYMENT_STATUS_PAID,
                        QUI\ERP\Constants::PAYMENT_STATUS_CANCELED,
                        QUI\ERP\Constants::PAYMENT_STATUS_DEBIT
                    ]
                ],
//                'time_for_payment' => [
//                    'type'  => '<=',
//                    'value' => \date('Y-m-d H:i:s')
//                ],
                'customer_id' => $User->getId(),
                'type' => InvoiceHandler::TYPE_INVOICE
            ]
        ]);

        $invoices = [];

        foreach ($result as $row) {
            try {
                $Invoice = $Invoices->get($row['id']);

                // Filter invoices that are considered paid (even if not in database)
                if ($Invoice->isPaid()) {
                    continue;
                }

                $invoices[] = $Invoices->get($row['id']);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $invoices;
    }

    /**
     * Parses invoice data to an open item
     *
     * @param Invoice $Invoice
     * @return Item
     */
    protected static function parseInvoiceToOpenItem(Invoice $Invoice)
    {
        $Item = new Item($Invoice->getCleanId(), self::DOCUMENT_TYPE_INVOICE);

        // Basic data
        $Item->setDocumentNo($Invoice->getId());
        $Item->setDate(\date_create($Invoice->getAttribute('c_date')));
        $Item->setDueDate(\date_create($Invoice->getAttribute('time_for_payment')));
        $Item->setGlobalProcessId($Invoice->getGlobalProcessId());

        // Invoice amounts
        $paidStatus = $Invoice->getPaidStatusInformation();
        $Item->setAmountPaid($paidStatus['paid']);
        $Item->setAmountOpen($paidStatus['toPay']);
        $Item->setAmountTotalNet($Invoice->getAttribute('nettosum'));
        $Item->setAmountTotalSum($Invoice->getAttribute('sum'));
//        $Item->setAmountTotal($Invoice->getAttribute('sum'));

        // VAT
        $vat = \json_decode($Invoice->getAttribute('vat_array'), true);
        $vatSum = 0;

        foreach ($vat as $vatEntry) {
            $vatSum += $vatEntry['sum'];
        }

        $Item->setAmountTotalVat($vatSum);
        $Item->setCurrency($Invoice->getCurrency());

        // Latest transaction date
        $transactions = InvoiceUtils::getTransactionsByInvoice($Invoice);

        if (!empty($transactions)) {
            // Sort by date
            \usort($transactions, function ($TransactionA, $TransactionB) {
                /**
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionA
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionB
                 */
                $DateA = \date_create($TransactionA->getDate());
                $DateB = \date_create($TransactionB->getDate());

                if ($DateA === $DateB) {
                    return 0;
                }

                return $DateA > $DateB ? -1 : 1;
            });

            $LatestTransactionDate = \date_create($transactions[0]->getDate());
            $Item->setLastPaymentDate($LatestTransactionDate);
        }

        // Days due
        $Now = \date_create();
        $TimeForPayment = \date_create($Invoice->getAttribute('time_for_payment'));

        if ($Now < $TimeForPayment) {
            $Item->setDaysDue(0);
        } else {
            $Item->setDaysDue($TimeForPayment->diff($Now)->days + 1);
        }

        // Check if dunning exist
        if (QUI::getPackageManager()->isInstalled('quiqqer/dunning')) {
            $DunningProcess = DunningsHandler::getInstance()->getDunningProcessByInvoiceId($Invoice->getCleanId());

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
     */
    protected static function getOpenOrders(QUI\Interfaces\Users\User $User)
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
            'customerId' => $User->getId(),
            'invoice_id' => null
        ];

        $Orders = OrderHandler::getInstance();
        $OrderStatusHandler = new OrderStatusHandler();

        try {
            $CancelledStatus = $OrderStatusHandler->getCancelledStatus();

            if ($CancelledStatus->getId()) {
                $where['status'] = [
                    'type' => 'NOT',
                    'value' => $CancelledStatus->getId()
                ];
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        $result = QUI::getDataBase()->fetch([
            'select' => ['id'],
            'from' => $Orders->table(),
            'where' => $where
        ]);

        $orders = [];

        foreach ($result as $row) {
            try {
                $orders[] = $Orders->get($row['id']);
            } catch (\Exception $Exception) {
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
     */
    protected static function parseOrderToOpenItem(QUI\ERP\Order\Order $Order)
    {
        $Item = new Item($Order->getCleanId(), self::DOCUMENT_TYPE_ORDER);

        // Basic data
        $Item->setDocumentNo($Order->getPrefixedId());
        $Item->setDate(\date_create($Order->getAttribute('c_date')));
        $Item->setGlobalProcessId($Order->getGlobalProcessId());

        if (!empty($Order->getAttribute('payment_time'))) {
            $Item->setDueDate(\date_create($Order->getAttribute('payment_time')));
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
            $Item->setAmountTotalVat(\array_sum(\array_column($calculations['vatArray'], 'sum')));
        }

        $Item->setCurrency($Order->getCurrency());

        // Latest transaction date
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($Order->getHash());

        if (!empty($transactions)) {
            // Sort by date
            \usort($transactions, function ($TransactionA, $TransactionB) {
                /**
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionA
                 * @var QUI\ERP\Accounting\Payments\Transactions\Transaction $TransactionB
                 */
                $DateA = \date_create($TransactionA->getDate());
                $DateB = \date_create($TransactionB->getDate());

                if ($DateA === $DateB) {
                    return 0;
                }

                return $DateA > $DateB ? -1 : 1;
            });

            $LatestTransactionDate = \date_create($transactions[0]->getDate());
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
        $OpenItemsList = self::getOpenItemsList($User);
        $items = $OpenItemsList->getItems();

        // If no open items exist -> Delete entry from db
        if (empty($items)) {
            QUI::getDataBase()->delete(
                self::getTable(),
                [
                    'userId' => $User->getId()
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
            QUI::getDataBase()->replace(
                self::getTable(),
                [
                    'userId' => $User->getId(),
                    'customerId' => $customerId,
                    'net_sum' => $values['netTotal'],
                    'total_sum' => $values['sumTotal'],
                    'open_sum' => $values['dueTotal'],
                    'paid_sum' => $values['paidTotal'],
                    'vat_sum' => $values['vatTotal'],
                    'open_items_count' => \count($OpenItemsList->getItemsByCurrencyCode($currency)),
                    'currency' => $currency
                ]
            );
        }

        // Clear cache used in backend administration
        QUI\Cache\Manager::clear('quiqqer/customer/openitems/' . $User->getId());
    }

    /**
     * Search open items records
     *
     * @param array $searchParams
     * @return array|int
     */
    public static function searchOpenItems(array $searchParams)
    {
        $Grid = new Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);

        $binds = [];
        $where = [];
        $countOnly = !empty($searchParams['count']);

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT *";
        }

        $sql .= " FROM `" . self::getTable() . "`";

        if (!empty($searchParams['search'])) {
            $searchColumns = [
                'customerId',
                'userId'
            ];

            $whereOr = [];

            foreach ($searchColumns as $searchColumn) {
                $whereOr[] = '`' . $searchColumn . '` LIKE :search';
            }

            if (!empty($whereOr)) {
                $where[] = '(' . implode(' OR ', $whereOr) . ')';

                $binds['search'] = [
                    'value' => '%' . $searchParams['search'] . '%',
                    'type' => \PDO::PARAM_STR
                ];
            }
        }

        if (!empty($searchParams['currency'])) {
            $where[] = '`currency` = \'' . Orthos::clear($searchParams['currency']) . '\'';
        }

        if (!empty($searchParams['userId'])) {
            $where[] = '`userId` = ' . (int)$searchParams['userId'];
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // ORDER
        if (!empty($searchParams['sortOn'])) {
            $sortOn = Orthos::clear($searchParams['sortOn']);

            switch ($sortOn) {
                case 'display_net_sum':
                    $sortOn = 'net_sum';
                    break;

                case 'display_vat_sum':
                    $sortOn = 'vat_sum';
                    break;

                case 'display_total_sum':
                    $sortOn = 'total_sum';
                    break;

                case 'display_paid_sum':
                    $sortOn = 'paid_sum';
                    break;

                case 'display_open_sum':
                    $sortOn = 'open_sum';
                    break;

                case 'currency_code':
                    $sortOn = 'currency';
                    break;
            }

            $order = "ORDER BY " . $sortOn;

            if (
                isset($searchParams['sortBy']) &&
                !empty($searchParams['sortBy'])
            ) {
                $order .= " " . Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " " . $order;
        } else {
            $sql .= " ORDER BY `userId` DESC";
        }

        // LIMIT
        if (
            !empty($gridParams['limit'])
            && !$countOnly
        ) {
            $sql .= " LIMIT " . $gridParams['limit'];
        } else {
            if (!$countOnly) {
                $sql .= " LIMIT " . (int)20;
            }
        }

        $Stmt = QUI::getPDO()->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        if ($countOnly) {
            return (int)current(current($result));
        }

        return $result;
    }

    /**
     * Parses a search result for display in backend GRID
     *
     * @param array $result
     * @return array
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
            } catch (\Exception $Exception) {
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
     * @param array $entries - Database rows form customer_open_items table
     * @param QUI\ERP\Currency\Currency $Currency
     * @return array - Totals prepared for backend display
     */
    public static function getTotals(array $entries, QUI\ERP\Currency\Currency $Currency)
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
