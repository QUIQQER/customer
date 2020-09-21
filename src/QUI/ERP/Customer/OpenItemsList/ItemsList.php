<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI;
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

        $this->amountNetTotal += $Item->getAmountTotalNet();
        $this->amountSumTotal += $Item->getAmountTotalSum();
        $this->amountVatTotal += $Item->getAmountTotalVat();
        $this->amountDueTotal += $Item->getAmountOpen();

        // Sort
        \usort($this->items, function ($ItemA, $ItemB) {
            /**
             * @var Item $ItemA
             * @var Item $ItemB
             */
            $DateA = $ItemA->getDate();
            $DateB = $ItemB->getDate();

            if ($DateA === $DateB) {
                return 0;
            }

            return $DateA < $DateB ? -1 : 1;
        });
    }

    /**
     * @return float
     */
    public function getAmountNetTotal(): float
    {
        return $this->amountNetTotal;
    }

    /**
     * @return float
     */
    public function getAmountVatTotal(): float
    {
        return $this->amountVatTotal;
    }

    /**
     * @return float
     */
    public function getAmountSumTotal(): float
    {
        return $this->amountSumTotal;
    }

    /**
     * @return float
     */
    public function getAmountDueTotal(): float
    {
        return $this->amountDueTotal;
    }

    /**
     * @return \DateTime
     */
    public function getDate(): \DateTime
    {
        return $this->Date;
    }

    /**
     * @return string
     */
    public function getDateFormatted()
    {
        if (empty($this->User)) {
            return $this->Date->format('Y-m-d H:i');
        }

        return $this->User->getLocale()->formatDate($this->Date->getTimestamp());
    }

    /**
     * @param \DateTime $Date
     */
    public function setDate(\DateTime $Date)
    {
        $this->Date = $Date;
    }

    /**
     * @param User $User
     */
    public function setUser(User $User)
    {
        $this->User = $User;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->User;
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return string
     */
    public function getListAsHtmlTable()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return '';
        }

        $Engine->assign([
            'this' => $this
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ItemList.html');
    }
}
