<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;

if (!class_exists(Invoice::class)) {
    class Invoice
    {
        public function getId(): int
        {
            return 0;
        }

        public function getPrefixedNumber(): string
        {
            return '';
        }

        public function getAttribute(string $name): mixed
        {
            return null;
        }

        public function getGlobalProcessId(): string
        {
            return '';
        }

        public function getUUID(): string
        {
            return '';
        }

        public function getPaidStatusInformation(): array
        {
            return [
                'paid' => 0,
                'toPay' => 0
            ];
        }

        public function getCurrency(): QUI\ERP\Currency\Currency
        {
            return QUI\ERP\Defaults::getCurrency();
        }

        public function getCustomer(): ?QUI\ERP\User
        {
            return null;
        }

        public function isPaid(): bool
        {
            return false;
        }
    }
}
