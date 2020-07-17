<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI\Users\User;

/**
 * Class ItemsList
 *
 * Represents a list of open items
 */
class ItemsList
{
    /**
     * @var float
     */
    protected $amountNetTotal = 0;

    /**
     * @var float
     */
    protected $amountVatTotal = 0;

    /**
     * @var float
     */
    protected $amountSumTotal = 0;

    /**
     * @var float
     */
    protected $amountDueTotal = 0;

    /**
     * @var \DateTime
     */
    protected $Date;

    /**
     * @var User
     */
    protected $User;

    /**
     * @var Item[]
     */
    protected $items;

    /**
     * Add an open item to the list
     *
     * @param Item $Item
     * @return void
     */
    public function addItem(Item $Item)
    {
        $this->items[] = $Item;
    }
}
