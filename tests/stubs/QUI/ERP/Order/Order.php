<?php

namespace QUI\ERP\Order;

use QUI;

if (!class_exists(Order::class)) {
    class Order extends AbstractOrder
    {
        public function getArticles(): QUI\ERP\Accounting\ArticleList
        {
            return new QUI\ERP\Accounting\ArticleList();
        }

        public function getCleanId(): int
        {
            return 0;
        }

        public function getPrefixedNumber(): string
        {
            return '';
        }

        public function getGlobalProcessId(): string
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
    }
}
