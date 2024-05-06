<?php

namespace QUI\ERP\Customer\OpenItemsList;

use DateTime;
use QUI;
use QUI\ERP\Currency\Currency;
use QUI\Locale;

use function date_create;

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
    protected string $title = '';

    /**
     * @var string
     */
    protected string $description = '';

    /**
     * @var int|string
     */
    protected string|int $documentId;

    /**
     * @var string
     */
    protected string $documentNo = '';

    /**
     * @var string
     */
    protected string $documentType;

    /**
     * @var DateTime
     */
    protected DateTime $Date;

    /**
     * @var DateTime
     */
    protected DateTime $DueDate;

    /**
     * @var DateTime|false
     */
    protected false|DateTime $LastPaymentDate = false;

    /**
     * @var float|int
     */
    protected int|float $amountPaid = 0;

    /**
     * @var int|float
     */
    protected int|float $amountOpen = 0;

    /**
     * @var int|float
     */
    protected int|float $amountTotalNet = 0;

    /**
     * @var int|float
     */
    protected int|float $amountTotalSum = 0;

    /**
     * @var int|float
     */
    protected int|float $amountTotalVat = 0;

    /**
     * @var int|float
     */
    protected int|float $amountTotal = 0;

    /**
     * @var int
     */
    protected int $daysDue = 0;

    /**
     * @var int|false
     */
    protected int|false $dunningLevel = false;

    /**
     * @var Currency
     */
    protected Currency $Currency;

    /**
     * @var Locale
     */
    protected Locale $Locale;

    protected ?string $globalProcessId = null;
    protected ?string $hash = null;

    /**
     * Item constructor.
     *
     * @param int|string $documentId - Internal document id
     * @param string $documentType - Type of document (e.g. invoice, order etc.)
     */
    public function __construct(int|string $documentId, string $documentType)
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
    public function setTitle(string $title): void
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
    public function setDescription(string $description): void
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
    public function setDaysDue(int $daysDue): void
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

        $Now = date_create();
        $Diff = $Now->diff($this->Date);

        return $Diff->days + 1;
    }

    /**
     * @return false|int
     */
    public function getDunningLevel(): bool|int
    {
        return $this->dunningLevel;
    }

    /**
     * @param bool|int $dunningLevel
     */
    public function setDunningLevel(bool|int $dunningLevel): void
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
    public function setLocale(Locale $Locale): void
    {
        $this->Locale = $Locale;
    }

    /**
     * @return int|string
     */
    public function getDocumentId(): int|string
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
    public function setDocumentNo(string $documentNo): void
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
     * @return DateTime
     */
    public function getDate(): DateTime
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
     * @param DateTime $Date
     */
    public function setDate(DateTime $Date): void
    {
        $this->Date = $Date;
    }

    /**
     * @return DateTime|false
     */
    public function getLastPaymentDate(): DateTime|bool
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
     * @param DateTime $LastPaymentDate
     */
    public function setLastPaymentDate(DateTime $LastPaymentDate): void
    {
        $this->LastPaymentDate = $LastPaymentDate;
    }

    /**
     * @return DateTime
     */
    public function getDueDate(): DateTime
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
     * @param DateTime $DueDate
     */
    public function setDueDate(DateTime $DueDate): void
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
    public function setAmountPaid(float $amountPaid): void
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
    public function setAmountOpen(float $amountOpen): void
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
    public function setAmountTotalNet(float $amountTotalNet): void
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
    public function setAmountTotalSum(float $amountTotalSum): void
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
    public function setAmountTotalVat(float $amountTotalVat): void
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
    public function setCurrency(Currency $Currency): void
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
