<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Order\Settings;
use QUI\ERP\Order\Order;

/**
 * Class Events
 *
 * Event handler for all events related to open items of customers
 */
class Events
{
    /**
     * quiqqer/payment-transactions: onTransactionCreate
     *
     * Update open records of user if a transaction was made against one of his open items
     *
     * @param Transaction $Transaction
     * @return void
     */
    public static function onTransactionCreate(Transaction $Transaction)
    {
        $userId = $Transaction->getAttribute('uid');

        if (empty($userId)) {
            return;
        }

        try {
            $User = QUI::getUsers()->get($userId);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        try {
            Handler::updateOpenItemsRecord($User);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * quiqqer/invoice: onQuiqqerInvoicePaymentStatusChanged
     *
     * Update open records of user if a transaction was made against one of his open items
     *
     * @param Invoice $Invoice
     * @param int $currentStatus
     * @param int $oldStatus
     * @return void
     */
    public static function onQuiqqerInvoicePaymentStatusChanged(
        Invoice $Invoice,
        int $currentStatus,
        int $oldStatus
    ) {
        try {
            $User = $Invoice->getCustomer();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        try {
            Handler::updateOpenItemsRecord($User);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * quiqqer/order: onQuiqqerOrderPaidStatusChanged
     *
     * Update open records of user if a transaction was made against one of his open items
     *
     * @param Order $Order
     * @param int $currentStatus
     * @param int $oldStatus
     * @return void
     */
    public static function onQuiqqerOrderPaidStatusChanged(
        Order $Order,
        int $currentStatus,
        int $oldStatus
    ) {
        try {
            $User = $Order->getCustomer();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        try {
            Handler::updateOpenItemsRecord($User);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * quiqqer/invoice: onQuiqqerInvoiceTemporaryInvoicePostEnd
     *
     * Update open records of user if an invoice is posted
     *
     * @param InvoiceTemporary $TempInvoice
     * @param Invoice $Invoice
     * @return void
     */
    public static function onQuiqqerInvoiceTemporaryInvoicePostEnd(
        InvoiceTemporary $TempInvoice,
        Invoice $Invoice
    ): void {
        try {
            $User = $Invoice->getCustomer();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        try {
            Handler::updateOpenItemsRecord($User);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * quiqqer/order: onQuiqqerOrderCreated
     *
     * Update open records of user if an order is created
     *
     * @param QUI\ERP\Order\Order $Order
     */
    public static function onQuiqqerOrderCreated(QUI\ERP\Order\Order $Order)
    {
        try {
            $Conf           = QUI::getPackage('quiqqer/customer')->getConfig();
            $considerOrders = $Conf->get('openItems', 'considerOrders');

            if (empty($considerOrders)) {
                return;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        if (!empty($Order->getAttribute('no_invoice_auto_create'))) {
            return;
        }

        // Do not track order that also are tracked via invoice
        if (Settings::getInstance()->createInvoiceOnOrder()) {
            return;
        }

        try {
            $User = $Order->getCustomer();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        try {
            Handler::updateOpenItemsRecord($User);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
