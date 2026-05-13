<?php

namespace QUI\ERP\Accounting\Invoice\Utils;

if (!class_exists(Invoice::class)) {
    class Invoice
    {
        public static function getTransactionsByInvoice(
            \QUI\ERP\Accounting\Invoice\Invoice $Invoice
        ): array {
            return [];
        }
    }
}
