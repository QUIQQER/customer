<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function invoiceTable(): string
        {
            return '';
        }

        public function get(int|string $id): Invoice
        {
            return new Invoice();
        }

        public function getInvoiceByHash(string $hash): Invoice
        {
            return new Invoice();
        }

        public function getTemporaryInvoice(string $hash): InvoiceTemporary
        {
            return new InvoiceTemporary();
        }
    }
}
