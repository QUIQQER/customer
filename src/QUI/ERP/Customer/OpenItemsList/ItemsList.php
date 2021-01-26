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
     * @var array
     */
    protected $totalAmountsByCurrency = [];

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
    protected $items = [];

    /**
     * Add an open item to the list
     *
     * @param Item $Item
     * @return void
     */
    public function addItem(Item $Item)
    {
        $this->items[] = $Item;

        // Calculate total amounts
        $Currency     = $Item->getCurrency();
        $currencyCode = $Currency->getCode();

        if (empty($this->totalAmountsByCurrency[$currencyCode])) {
            $this->totalAmountsByCurrency[$currencyCode] = [
                'netTotal'           => 0,
                'netTotalFormatted'  => '',
                'vatTotal'           => 0,
                'vatTotalFormatted'  => '',
                'sumTotal'           => 0,
                'sumTotalFormatted'  => '',
                'dueTotal'           => 0,
                'dueTotalFormatted'  => '',
                'paidTotal'          => 0,
                'paidTotalFormatted' => ''
            ];
        }

        $this->totalAmountsByCurrency[$currencyCode]['netTotal']          += $Item->getAmountTotalNet();
        $this->totalAmountsByCurrency[$currencyCode]['netTotalFormatted'] = $Currency->format(
            $this->totalAmountsByCurrency[$currencyCode]['netTotal']
        );

        $this->totalAmountsByCurrency[$currencyCode]['sumTotal']          += $Item->getAmountTotalSum();
        $this->totalAmountsByCurrency[$currencyCode]['sumTotalFormatted'] = $Currency->format(
            $this->totalAmountsByCurrency[$currencyCode]['sumTotal']
        );

        $this->totalAmountsByCurrency[$currencyCode]['dueTotal']          += $Item->getAmountOpen();
        $this->totalAmountsByCurrency[$currencyCode]['dueTotalFormatted'] = $Currency->format(
            $this->totalAmountsByCurrency[$currencyCode]['dueTotal']
        );

        $this->totalAmountsByCurrency[$currencyCode]['paidTotal']          += $Item->getAmountPaid();
        $this->totalAmountsByCurrency[$currencyCode]['paidTotalFormatted'] = $Currency->format(
            $this->totalAmountsByCurrency[$currencyCode]['paidTotal']
        );

        $this->totalAmountsByCurrency[$currencyCode]['vatTotal']          += $Item->getAmountTotalVat();
        $this->totalAmountsByCurrency[$currencyCode]['vatTotalFormatted'] = $Currency->format(
            $this->totalAmountsByCurrency[$currencyCode]['vatTotal']
        );

        // Sort items by date ASC
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
     * @return array
     */
    public function getTotalAmountsByCurrency(): array
    {
        return $this->totalAmountsByCurrency;
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
    public function getDateFormatted(): string
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
     * @param string $currencyCode
     * @return Item[]
     */
    public function getItemsByCurrencyCode(string $currencyCode): array
    {
        $items = [];

        foreach ($this->items as $Item) {
            if ($Item->getCurrency()->getCode() === $currencyCode) {
                $items[] = $Item;
            }
        }

        return $items;
    }

    /**
     * @return string
     */
    public function getListAsHtmlTable(): string
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        $Engine->assign([
            'this'    => $this,
            'isEmpty' => \count($this->items) === 0
        ]);

        $body = '<style>'.\file_get_contents(\dirname(__FILE__).'/ItemList.css').'</style>';
        $body .= $Engine->fetch(\dirname(__FILE__).'/ItemList.html');

        return $body;
    }
}
