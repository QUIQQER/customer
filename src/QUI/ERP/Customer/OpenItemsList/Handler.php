<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\User as ERPUser;
use QUI;

/**
 * Class Handler
 *
 * Generates open items lists for customers
 */
class Handler
{
    /**
     * Generate an open items list for a user
     *
     * @param ERPUser $User
     * @return ItemsList
     */
    public static function getOpenItemsList(ERPUser $User)
    {
        $List = new ItemsList();

        // Fetch open invoices
        $invoices = self::getOpenInvoices($User);


        // Fetch open dunnings
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

        // Fetch all overdue invoices that are not already tracked
        $result = QUI::getDataBase()->fetch([
            'select' => ['id'],
            'from'   => $Invoices->invoiceTable(),
            'where'  => [
                'paid_status'      => [
                    'type'  => 'NOT IN',
                    'value' => [
                        Invoice::PAYMENT_STATUS_PAID,
                        Invoice::PAYMENT_STATUS_CANCELED
                    ]
                ],
                'time_for_payment' => [
                    'type'  => '<=',
                    'value' => \date('Y-m-d H:i:s')
                ],
                'customer_id'      => $User->getId()
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

        
    }
}
