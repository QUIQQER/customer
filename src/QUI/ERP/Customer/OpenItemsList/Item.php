<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI\ERP\Currency\Currency;

/**
 * Class Item
 *
 * Represents on open payment obligation by the customer
 */
class Item
{
    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var string
     */
    protected $documentNo = '';

    /**
     * @var \DateTime
     */
    protected $Date;

    /**
     * @var \DateTime
     */
    protected $DueDate;

    /**
     * @var float
     */
    protected $amountPaid = 0;

    /**
     * @var float
     */
    protected $amountDueNet = 0;

    /**
     * @var float
     */
    protected $amountDueSum = 0;

    /**
     * @var float
     */
    protected $amountDueVat = 0;

    /**
     * @var float
     */
    protected $amountTotal = 0;

    /**
     * @var int
     */
    protected $daysDue = 0;

    /**
     * @var Currency
     */
    protected $Currency;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getDocumentNo(): string
    {
        return $this->documentNo;
    }

    /**
     * @param string $documentNo
     */
    public function setDocumentNo(string $documentNo)
    {
        $this->documentNo = $documentNo;
    }

    /**
     * @return \DateTime
     */
    public function getDate(): \DateTime
    {
        return $this->Date;
    }

    /**
     * @param \DateTime $Date
     */
    public function setDate(\DateTime $Date)
    {
        $this->Date = $Date;
    }

    /**
     * @return \DateTime
     */
    public function getDueDate(): \DateTime
    {
        return $this->DueDate;
    }

    /**
     * @param \DateTime $DueDate
     */
    public function setDueDate(\DateTime $DueDate)
    {
        $this->DueDate = $DueDate;
    }

    /**
     * @return float
     */
    public function getAmountPaid(): float
    {
        return $this->amountPaid;
    }

    /**
     * @param float $amountPaid
     */
    public function setAmountPaid(float $amountPaid)
    {
        $this->amountPaid = $amountPaid;
    }

    /**
     * @return float
     */
    public function getAmountDueNet(): float
    {
        return $this->amountDueNet;
    }

    /**
     * @param float $amountDueNet
     */
    public function setAmountDueNet(float $amountDueNet)
    {
        $this->amountDueNet = $amountDueNet;
    }

    /**
     * @return float
     */
    public function getAmountDueSum(): float
    {
        return $this->amountDueSum;
    }

    /**
     * @param float $amountDueSum
     */
    public function setAmountDueSum(float $amountDueSum)
    {
        $this->amountDueSum = $amountDueSum;
    }

    /**
     * @return float
     */
    public function getAmountDueVat(): float
    {
        return $this->amountDueVat;
    }

    /**
     * @param float $amountDueVat
     */
    public function setAmountDueVat(float $amountDueVat)
    {
        $this->amountDueVat = $amountDueVat;
    }

    /**
     * @return float
     */
    public function getAmountTotal(): float
    {
        return $this->amountTotal;
    }

    /**
     * @param float $amountTotal
     */
    public function setAmountTotal(float $amountTotal)
    {
        $this->amountTotal = $amountTotal;
    }

    /**
     * @return Currency
     */
    public function getCurrency(): Currency
    {
        return $this->Currency;
    }

    /**
     * @param Currency $Currency
     */
    public function setCurrency(Currency $Currency)
    {
        $this->Currency = $Currency;
    }
}
