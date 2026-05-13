<?php

namespace QUI\ERP\Order\Controls\OrderProcess;

use QUI\ERP\Order\Order;

if (!class_exists(CustomerData::class)) {
    class CustomerData
    {
        public function getOrder(): Order
        {
            return new Order();
        }
    }
}
