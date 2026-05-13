<?php

namespace QUI\ERP\Order;

if (!class_exists(Settings::class)) {
    class Settings
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function createInvoiceOnOrder(): bool
        {
            return false;
        }
    }
}
