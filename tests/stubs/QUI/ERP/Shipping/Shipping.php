<?php

namespace QUI\ERP\Shipping;

use QUI;

if (!class_exists(Shipping::class)) {
    class Shipping extends QUI\Utils\Singleton
    {
        /** @return list<QUI\CRUD\Child> */
        public function getShippingList(): array
        {
            return [];
        }

        public function getShippingEntry(int | string $id): QUI\CRUD\Child
        {
            throw new QUI\Exception('Shipping is not installed.');
        }
    }
}
