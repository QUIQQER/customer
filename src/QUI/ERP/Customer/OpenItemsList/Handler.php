<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\User as ERPUser;
use QUI;
use QUI\ERP\Accounting\Dunning\Handler as DunningsHandler;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;
use QUI\ERP\Customer\Customers;

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
    const PERMISSION_OPENITEMS_VIEW        = 'quiqqer.customer.OpenItemsList.view';
    const PERMISSION_OPENITEMS_ADD_PAYMENT = 'quiqqer.customer.OpenItemsList.addPayment';

    const DOCUMENT_TYPE_INVOICE = 'invoice';
    const DOCUMENT_TYPE_ORDER   = 'order';

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

        foreach ($invoices as $Invoice) {
            $List->addItem(self::parseInvoiceToOpenItem($Invoice));
        }

        // @todo Fetch open dunnings

        return $List;
    }

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
            'from'   => $Invoices->invoiceTable(),
            'where'  => [
                'paid_status'      => [
                    'type'  => 'NOT IN',
                    'value' => [
                        QUI\ERP\Constants::PAYMENT_STATUS_PAID,
                        QUI\ERP\Constants::PAYMENT_STATUS_CANCELED
                    ]
                ],
                'time_for_payment' => [
                    'type'  => '<=',
                    'value' => \date('Y-m-d H:i:s')
                ],
                'customer_id'      => $User->getId(),
                'type'             => InvoiceHandler::TYPE_INVOICE
            ]
        ]);

        $invoices = [];

        foreach ($result as $row) {
            try {
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
        $Item = new Item(self::DOCUMENT_TYPE_INVOICE);

        // Basic data
        $Item->setDocumentNo($Invoice->getId());
        $Item->setDate(\date_create($Invoice->getAttribute('c_date')));
        $Item->setDueDate(\date_create($Invoice->getAttribute('time_for_payment')));

        // Invoice amounts
        $paidStatus = $Invoice->getPaidStatusInformation();
        $Item->setAmountPaid($paidStatus['paid']);
        $Item->setAmountOpen($paidStatus['toPay']);
        $Item->setAmountTotalNet($Invoice->getAttribute('nettosum'));
        $Item->setAmountTotalSum($Invoice->getAttribute('sum'));
//        $Item->setAmountTotal($Invoice->getAttribute('sum'));

        // VAT
        $vat    = \json_decode($Invoice->getAttribute('vat_array'), true);
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
        $Now            = \date_create();
        $TimeForPayment = \date_create($Invoice->getAttribute('time_for_payment'));
        $Item->setDaysDue($TimeForPayment->diff($Now)->days + 1);

        // Check if dunning exist
        if (QUI::getPackageManager()->isInstalled('quiqqer/dunning')) {
            $DunningProcess = DunningsHandler::getInstance()->getDunningProcessByInvoiceId($Invoice->getCleanId());

            if ($DunningProcess && $DunningProcess->getCurrentDunning()) {
                $Item->setDunningLevel($DunningProcess->getCurrentDunning()->getDunningLevel()->getLevel());
            }
        }

        return $Item;
    }

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
        $items         = $OpenItemsList->getItems();

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

        // Parse open items to db entry
        foreach ($OpenItemsList->getTotalAmountsByCurrency() as $currency => $values) {
            QUI::getDataBase()->replace(
                self::getTable(),
                [
                    'userId'           => $User->getId(),
                    'customerId'       => $User->getAttribute('customerId'),
                    'net_sum'          => $values['netTotal'],
                    'total_sum'        => $values['sumTotal'],
                    'open_sum'         => $values['dueTotal'],
                    'paid_sum'         => $values['paidTotal'],
                    'vat_sum'          => $values['vatTotal'],
                    'open_items_count' => \count($OpenItemsList->getItemsByCurrencyCode($currency)),
                    'currency'         => $currency
                ]
            );
        }
    }

    /**
     * Search open items records
     *
     * @param array $searchParams
     * @return array|int
     */
    public static function searchOpenItems(array $searchParams)
    {
        $Grid       = new Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);

        $binds     = [];
        $where     = [];
        $countOnly = !empty($searchParams['count']);

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT *";
        }

        $sql .= " FROM `".self::getTable()."`";

        if (!empty($searchParams['search'])) {
            $searchColumns = [
                'customerId',
                'userId'
            ];

            $whereOr = [];

            foreach ($searchColumns as $searchColumn) {
                $whereOr[] = '`'.$searchColumn.'` LIKE :search';
            }

            if (!empty($whereOr)) {
                $where[] = '('.implode(' OR ', $whereOr).')';

                $binds['search'] = [
                    'value' => '%'.$searchParams['search'].'%',
                    'type'  => \PDO::PARAM_STR
                ];
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE ".implode(" AND ", $where);
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

            $order = "ORDER BY ".$sortOn;

            if (isset($searchParams['sortBy']) &&
                !empty($searchParams['sortBy'])
            ) {
                $order .= " ".Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " ".$order;
        } else {
            $sql .= " ORDER BY `userId` DESC";
        }

        // LIMIT
        if (!empty($gridParams['limit'])
            && !$countOnly
        ) {
            $sql .= " LIMIT ".$gridParams['limit'];
        } else {
            if (!$countOnly) {
                $sql .= " LIMIT ".(int)20;
            }
        }

        $Stmt = QUI::getPDO()->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':'.$var, $bind['value'], $bind['type']);
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
     */
    public static function parseForGrid(array $result): array
    {
        foreach ($result as $k => $row) {
            $Currency = new QUI\ERP\Currency\Currency($row['currency']);

            $sumColumns = [
                'net_sum',
                'vat_sum',
                'total_sum',
                'paid_sum',
                'open_sum'
            ];

            foreach ($sumColumns as $column) {
                $row['display_'.$column] = $Currency->format($row[$column]);
            }

            try {
                $Customer             = QUI::getUsers()->get($row['userId']);
                $row['customer_name'] = $Customer->getName();
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                $row['customer_name'] = '-';
            }

            $result[$k] = $row;
        }

        return $result;
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
