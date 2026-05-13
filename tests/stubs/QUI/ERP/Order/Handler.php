<?php

namespace QUI\ERP\Order;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function table(): string
        {
            return '';
        }

        public function get(int|string $id): Order
        {
            return new Order();
        }

        public function getOrderByHash(string $hash): Order
        {
            return new Order();
        }
    }
}
