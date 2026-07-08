<?php

namespace QUI\ERP\Order;

abstract class AbstractOrder
{
    public function getCustomer(): ?\QUI\ERP\User
    {
    }
}
