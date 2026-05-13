<?php

namespace QUI\ERP\Order;

if (!class_exists(AbstractOrder::class)) {
    abstract class AbstractOrder
    {
        public function getAttribute(string $name): mixed
        {
            return null;
        }

        public function getCustomer(): ?\QUI\ERP\User
        {
            return null;
        }

        public function getUUID(): string
        {
            return '';
        }
    }
}
