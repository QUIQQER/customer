<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\User as ERPUser;
use QUI;
use QUI\ERP\Accounting\Dunning\Handler as DunningsHandler;

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
     * Generate an open items list for a user
     *
     * @param ERPUser $User
     * @return ItemsList
     */
    public static function getOpenItemsList(ERPUser $User)
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
     * @param ERPUser $User
     * @return Invoice[]
     */
    protected static function getOpenInvoices(ERPUser $User)
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
        $Item = new Item();

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
     * @param ERPUser $User
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function updateOpenItemsRecord(ERPUser $User): void
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
     * @return array
     */
    public static function search(array $searchParams): array
    {

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
