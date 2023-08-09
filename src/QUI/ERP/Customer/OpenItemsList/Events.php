<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Order\Settings;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\Handler as OrderHandler;

use function json_decode;

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
        // Get invoice by hash
        try {
            $Invoice = InvoiceHandler::getInstance()->getInvoiceByHash($Transaction->getHash());
            $User = $Invoice->getCustomer();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            // Get order by hash
            try {
                $Order = OrderHandler::getInstance()->getOrderByHash($Transaction->getHash());
                $User = $Order->getCustomer();
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
                return;
            }
        }

        // Prefer LIVE user instead of invoice user
        $LiveErpUser = self::getLiveErpUser($User->getId());

        if ($LiveErpUser) {
            $User = $LiveErpUser;
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

            // Prefer LIVE user instead of invoice user
            $LiveErpUser = self::getLiveErpUser($User->getId());

            if ($LiveErpUser) {
                $User = $LiveErpUser;
            }
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

            // Prefer LIVE user instead of invoice user
            $LiveErpUser = self::getLiveErpUser($User->getId());

            if ($LiveErpUser) {
                $User = $LiveErpUser;
            }
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

            // Prefer LIVE user instead of invoice user
            $LiveErpUser = self::getLiveErpUser($User->getId());

            if ($LiveErpUser) {
                $User = $LiveErpUser;
            }
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
     * Update open records of user if an order changes
     *
     * @param int $orderId
     * @param array $orderAttributes
     * @return void
     */
    public static function onQuiqqerOrderDelete(int $orderId, array $orderAttributes): void
    {
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $considerOrders = $Conf->get('openItems', 'considerOrders');

            if (empty($considerOrders)) {
                return;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        // Do not track order that also are tracked via invoice
        if (Settings::getInstance()->createInvoiceOnOrder()) {
            return;
        }

        try {
            // Prefer LIVE user instead of invoice user
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
     * Update open records of user if an order changes
     *
     * @param QUI\ERP\Order\Order $Order
     */
    public static function onQuiqqerOrderCreated(QUI\ERP\Order\Order $Order)
    {
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
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

            // Prefer LIVE user instead of invoice user
            $LiveErpUser = self::getLiveErpUser($User->getId());

            if ($LiveErpUser) {
                $User = $LiveErpUser;
            }
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
     * Get ERP user from LIVE user data based on $userId from invoice or order
     *
     * @param int $userId
     * @return QUI\ERP\User|false
     */
    protected static function getLiveErpUser(int $userId)
    {
        try {
            $User = QUI::getUsers()->get($userId);
        } catch (\Exception $Exception) {
            if ($Exception->getCode() !== 404) {
                QUI\System\Log::writeException($Exception);
            }

            return false;
        }

        try {
            return QUI\ERP\User::convertUserToErpUser($User);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }
}
