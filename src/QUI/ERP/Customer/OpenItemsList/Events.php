<?php

namespace QUI\ERP\Customer\OpenItemsList;

use Exception;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\Settings;
use QUI\ERP\User;
use QUI\ERP\Order\AbstractOrder;

use function json_decode;

/**
 * Class Events
 *
 * Event handler for all events related to open items of customers
 */
class Events
{
    /**
     * Refresh the persisted open-items snapshot for a customer based on the live ERP user.
     *
     * @param QUI\ERP\User $User
     * @return void
     */
    protected static function syncOpenItemsRecord(QUI\ERP\User $User): void
    {
        $LiveErpUser = self::getLiveErpUser($User->getUUID());

        if ($LiveErpUser) {
            $User = $LiveErpUser;
        }

        Handler::updateOpenItemsRecord($User);
    }

    /**
     * quiqqer/payment-transactions: onTransactionCreate
     *
     * Update open records of user if a transaction was made against one of his open items
     *
     * @param Transaction $Transaction
     * @return void
     */
    public static function onTransactionCreate(Transaction $Transaction): void
    {
        // Get invoice by hash
        try {
            $Invoice = InvoiceHandler::getInstance()->getInvoiceByHash($Transaction->getHash());
            $User = $Invoice->getCustomer();
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            // Get order by hash
            try {
                $Order = OrderHandler::getInstance()->getOrderByHash($Transaction->getHash());
                $User = $Order->getCustomer();
            } catch (Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
                return;
            }
        }

        try {
            self::syncOpenItemsRecord($User);
        } catch (Exception $Exception) {
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
    ): void {
        try {
            self::syncOpenItemsRecord($Invoice->getCustomer());
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }
    }

    /**
     * quiqqer/invoice: quiqqerInvoiceLinkTransaction
     *
     * Update open records of user after linking an existing transaction to an invoice.
     *
     * @param Invoice $Invoice
     * @param Transaction $Transaction
     * @return void
     */
    public static function onQuiqqerInvoiceLinkTransaction(
        Invoice $Invoice,
        Transaction $Transaction
    ): void {
        try {
            self::syncOpenItemsRecord($Invoice->getCustomer());
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * quiqqer/order: onQuiqqerOrderPaidStatusChanged
     *
     * Update open records of user if a transaction was made against one of his open items
     *
     * @param AbstractOrder $Order
     * @param int $currentStatus
     * @param int $oldStatus
     * @return void
     */
    public static function onQuiqqerOrderPaidStatusChanged(
        AbstractOrder $Order,
        int $currentStatus,
        int $oldStatus
    ): void {
        try {
            self::syncOpenItemsRecord($Order->getCustomer());
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }
    }

    /**
     * quiqqer/order: quiqqerOrderLinkTransaction
     *
     * Update open records of user after linking an existing transaction to an order.
     *
     * @param AbstractOrder $Order
     * @param Transaction $Transaction
     * @return void
     */
    public static function onQuiqqerOrderLinkTransaction(
        AbstractOrder $Order,
        Transaction $Transaction
    ): void {
        try {
            self::syncOpenItemsRecord($Order->getCustomer());
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
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
            self::syncOpenItemsRecord($Invoice->getCustomer());
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }
    }

    /**
     * quiqqer/order: onQuiqqerOrderCreated
     *
     * Update open records of user if an order changes
     *
     * @param int|string $orderId
     * @param array $orderAttributes
     * @return void
     */
    public static function onQuiqqerOrderDelete(int|string $orderId, array $orderAttributes): void
    {
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $considerOrders = $Conf->get('openItems', 'considerOrders');

            if (empty($considerOrders)) {
                return;
            }
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        // Do not track order that also are tracked via invoice
        if (Settings::getInstance()->createInvoiceOnOrder()) {
            return;
        }

        try {
            // Prefer: LIVE user instead of invoice user
            $User = self::getLiveErpUser($orderAttributes['customerId']);

            if (!$User) {
                $customerData = json_decode($orderAttributes['customer'], true);
                $customerData['isCompany'] = !empty($customerData['company']);

                if (!empty($customerData['address']['country'])) {
                    $customerData['country'] = $customerData['address']['country'];
                } else {
                    $customerData['country'] = QUI\ERP\Defaults::getCountry()->getCode();
                }

                $User = new QUI\ERP\User($customerData);
            }
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return;
        }

        try {
            self::syncOpenItemsRecord($User);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * quiqqer/order: onQuiqqerOrderCreated
     *
     * Update open records of user if an order changes
     *
     * @param QUI\ERP\Order\AbstractOrder $Order
     */
    public static function onQuiqqerOrderCreated(QUI\ERP\Order\AbstractOrder $Order): void
    {
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $considerOrders = $Conf->get('openItems', 'considerOrders');

            if (empty($considerOrders)) {
                return;
            }
        } catch (Exception $Exception) {
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
            self::syncOpenItemsRecord($Order->getCustomer());
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * Get ERP user from LIVE user data based on $userId from invoice or order
     *
     * @param int|string $userId
     * @return User|false
     */
    protected static function getLiveErpUser(int|string $userId): bool|QUI\ERP\User
    {
        try {
            $User = QUI::getUsers()->get($userId);
        } catch (Exception $Exception) {
            if ($Exception->getCode() !== 404) {
                QUI\System\Log::writeException($Exception);
            }

            return false;
        }

        try {
            return QUI\ERP\User::convertUserToErpUser($User);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }
}
