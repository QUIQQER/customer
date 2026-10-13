<?php

namespace QUI\ERP\Accounting\Payments;

use QUI;

if (!class_exists(Payments::class)) {
    class Payments extends QUI\Utils\Singleton
    {
        /** @return list<QUI\CRUD\Child> */
        public function getPayments(): array
        {
            return [];
        }

        public function getPayment(int | string $id): QUI\CRUD\Child
        {
            throw new QUI\Exception('Payments are not installed.');
        }
    }
}
