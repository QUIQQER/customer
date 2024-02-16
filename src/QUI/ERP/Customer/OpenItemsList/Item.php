<?php

namespace QUI\ERP\Customer\OpenItemsList;

use QUI;
use QUI\ERP\Currency\Currency;
use QUI\Locale;

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
     * @var int|string
     */
    protected $documentId;

    /**
     * @var string
     */
    protected $documentNo = '';

    /**
     * @var string
     */
    protected $documentType;

    /**
     * @var \DateTime
     */
    protected $Date;

    /**
     * @var \DateTime
     */
    protected $DueDate;

    /**
     * @var \DateTime|false
     */
    protected $LastPaymentDate = false;

    /**
     * @var float
     */
    protected $amountPaid = 0;

    /**
     * @var float
     */
    protected $amountOpen = 0;

    /**
     * @var float
     */
    protected $amountTotalNet = 0;

    /**
     * @var float
     */
    protected $amountTotalSum = 0;

    /**
     * @var float
     */
    protected $amountTotalVat = 0;

    /**
     * @var float
     */
    protected $amountTotal = 0;

    /**
     * @var int
     */
    protected $daysDue = 0;

    /**
     * @var int|false
     */
    protected $dunningLevel = false;

    /**
     * @var Currency
     */
    protected $Currency;

    /**
     * @var Locale
     */
    protected $Locale;

    protected ?string $globalProcessId = null;
    protected ?string $hash = null;

    /**
     * Item constructor.
     *
     * @param int|string $documentId - Internal document id
     * @param string $documentType - Type of document (e.g. invoice, order etc.)
     */
    public function __construct($documentId, string $documentType)
    {
        $this->documentId = $documentId;
        $this->documentType = $documentType;
    }

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
     * @return int
     */
    public function getDaysDue(): int
    {
        return $this->daysDue;
    }

    /**
     * @param int $daysDue
     */
    public function setDaysDue(int $daysDue)
    {
        $this->daysDue = $daysDue;
    }

    /**
     * @return int
     */
    public function getDaysOpen(): int
    {
        if (empty($this->Date)) {
            return 0;
        }

        $Now = \date_create();
        $Diff = $Now->diff($this->Date);

        return $Diff->days + 1;
    }

    /**
     * @return false|int
     */
    public function getDunningLevel()
    {
        return $this->dunningLevel;
    }

    /**
     * @param false|int $dunningLevel
     */
    public function setDunningLevel($dunningLevel)
    {
        $this->dunningLevel = $dunningLevel;
    }

    /**
     * @return Locale
     */
    public function getLocale(): Locale
    {
        if (!empty($this->Locale)) {
            return $this->Locale;
        }

        $this->Locale = QUI::getLocale();

        return $this->Locale;
    }

    /**
     * @param Locale $Locale
     */
    public function setLocale(Locale $Locale)
    {
        $this->Locale = $Locale;
    }

    /**
     * @return int|string
     */
    public function getDocumentId()
    {
        return $this->documentId;
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
     * @return string
     */
    public function getDocumentType(): string
    {
        return $this->documentType;
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
        if (empty($this->Date)) {
            return '-';
        }

        return $this->getLocale()->formatDate($this->Date->getTimestamp());
    }

    /**
     * @param \DateTime $Date
     */
    public function setDate(\DateTime $Date)
    {
        $this->Date = $Date;
    }

    /**
     * @return \DateTime|false
     */
    public function getLastPaymentDate()
    {
        return $this->LastPaymentDate;
    }

    /**
     * @return string
     */
    public function getLastPaymentDateFormatted(): string
    {
        if (empty($this->LastPaymentDate)) {
            return '-';
        }

        return $this->getLocale()->formatDate($this->LastPaymentDate->getTimestamp());
    }

    /**
     * @param \DateTime $LastPaymentDate
     */
    public function setLastPaymentDate(\DateTime $LastPaymentDate)
    {
        $this->LastPaymentDate = $LastPaymentDate;
    }

    /**
     * @return \DateTime
     */
    public function getDueDate(): \DateTime
    {
        return $this->DueDate;
    }

    /**
     * @return string
     */
    public function getDueDateFormatted(): string
    {
        if (empty($this->DueDate)) {
            return '-';
        }

        return $this->getLocale()->formatDate($this->DueDate->getTimestamp());
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
     * @return string
     */
    public function getAmountPaidFormatted(): string
    {
        return $this->Currency->format($this->amountPaid, $this->getLocale());
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
    public function getAmountOpen(): float
    {
        return $this->amountOpen;
    }

    /**
     * @return string
     */
    public function getAmountOpenFormatted(): string
    {
        return $this->Currency->format($this->amountOpen, $this->getLocale());
    }

    /**
     * @param float $amountOpen
     */
    public function setAmountOpen(float $amountOpen)
    {
        $this->amountOpen = $amountOpen;
    }

    /**
     * @return float
     */
    public function getAmountTotalNet(): float
    {
        return $this->amountTotalNet;
    }

    /**
     * @return string
     */
    public function getAmountTotalNetFormatted(): string
    {
        return $this->Currency->format($this->amountTotalNet, $this->getLocale());
    }

    /**
     * @param float $amountTotalNet
     */
    public function setAmountTotalNet(float $amountTotalNet)
    {
        $this->amountTotalNet = $amountTotalNet;
    }

    /**
     * @return float
     */
    public function getAmountTotalSum(): float
    {
        return $this->amountTotalSum;
    }

    /**
     * @return string
     */
    public function getAmountTotalSumFormatted(): string
    {
        return $this->Currency->format($this->amountTotalSum, $this->getLocale());
    }

    /**
     * @param float $amountTotalSum
     */
    public function setAmountTotalSum(float $amountTotalSum)
    {
        $this->amountTotalSum = $amountTotalSum;
    }

    /**
     * @return float
     */
    public function getAmountTotalVat(): float
    {
        return $this->amountTotalVat;
    }

    /**
     * @return string
     */
    public function getAmountTotalVatFormatted(): string
    {
        return $this->Currency->format($this->amountTotalVat, $this->getLocale());
    }

    /**
     * @param float $amountTotalVat
     */
    public function setAmountTotalVat(float $amountTotalVat)
    {
        $this->amountTotalVat = $amountTotalVat;
    }

//    /**
//     * @return float
//     */
//    public function getAmountTotal(): float
//    {
//        return $this->amountTotal;
//    }
//
//    /**
//     * @return string
//     */
//    public function getAmountTotalFormatted()
//    {
//        return $this->Currency->format($this->amountTotal, $this->getLocale());
//    }
//
//    /**
//     * @param float $amountTotal
//     */
//    public function setAmountTotal(float $amountTotal)
//    {
//        $this->amountTotal = $amountTotal;
//    }

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

    public function getGlobalProcessId(): ?string
    {
        return $this->globalProcessId;
    }

    public function setGlobalProcessId(?string $globalProcessId): void
    {
        $this->globalProcessId = $globalProcessId;
    }

    /**
     * @return string|null
     */
    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * @param string|null $hash
     */
    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }
}
