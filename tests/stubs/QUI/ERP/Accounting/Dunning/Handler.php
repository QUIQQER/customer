<?php

namespace QUI\ERP\Accounting\Dunning;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function getDunningProcessByInvoiceId(int|string $invoiceId): ?DunningProcess
        {
            return null;
        }
    }
}
