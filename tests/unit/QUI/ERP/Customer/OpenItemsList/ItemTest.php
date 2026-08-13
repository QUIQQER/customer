<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\OpenItemsList;

use DateTime;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Currency\Currency;
use QUI\Locale;

final class ItemTest extends TestCase
{
    public function testScalarAndObjectPropertiesRoundTrip(): void
    {
        $Item = new Item('document-42', 'invoice');
        $Date = new DateTime('-2 days');
        $DueDate = new DateTime('+10 days');
        $LastPaymentDate = new DateTime('-1 day');
        $Locale = $this->createMock(Locale::class);
        $Locale->method('formatDate')
            ->willReturnCallback(static fn (int $timestamp): string => 'date:' . $timestamp);
        $Currency = $this->createMock(Currency::class);
        $Currency->method('format')
            ->willReturnCallback(static fn (float|int $amount): string => 'amount:' . $amount);

        $Item->setTitle('Test invoice');
        $Item->setDescription('Open invoice description');
        $Item->setDocumentNo('INV-42');
        $Item->setDate($Date);
        $Item->setDueDate($DueDate);
        $Item->setLastPaymentDate($LastPaymentDate);
        $Item->setDaysDue(10);
        $Item->setDunningLevel(2);
        $Item->setAmountPaid(12.5);
        $Item->setAmountOpen(87.5);
        $Item->setAmountTotalNet(84.03);
        $Item->setAmountTotalVat(15.97);
        $Item->setAmountTotalSum(100.0);
        $Item->setCurrency($Currency);
        $Item->setLocale($Locale);
        $Item->setGlobalProcessId('process-42');
        $Item->setHash('hash-42');

        self::assertSame('document-42', $Item->getDocumentId());
        self::assertSame('invoice', $Item->getDocumentType());
        self::assertSame('Test invoice', $Item->getTitle());
        self::assertSame('Open invoice description', $Item->getDescription());
        self::assertSame('INV-42', $Item->getDocumentNo());
        self::assertSame($Date, $Item->getDate());
        self::assertSame($DueDate, $Item->getDueDate());
        self::assertSame($LastPaymentDate, $Item->getLastPaymentDate());
        self::assertSame(10, $Item->getDaysDue());
        self::assertGreaterThanOrEqual(2, $Item->getDaysOpen());
        self::assertSame(2, $Item->getDunningLevel());
        self::assertSame(12.5, $Item->getAmountPaid());
        self::assertSame(87.5, $Item->getAmountOpen());
        self::assertSame(84.03, $Item->getAmountTotalNet());
        self::assertSame(15.97, $Item->getAmountTotalVat());
        self::assertSame(100.0, $Item->getAmountTotalSum());
        self::assertSame($Currency, $Item->getCurrency());
        self::assertSame($Locale, $Item->getLocale());
        self::assertSame('process-42', $Item->getGlobalProcessId());
        self::assertSame('hash-42', $Item->getHash());
        self::assertSame('date:' . $Date->getTimestamp(), $Item->getDateFormatted());
        self::assertSame('date:' . $DueDate->getTimestamp(), $Item->getDueDateFormatted());
        self::assertSame('date:' . $LastPaymentDate->getTimestamp(), $Item->getLastPaymentDateFormatted());
        self::assertSame('amount:12.5', $Item->getAmountPaidFormatted());
        self::assertSame('amount:87.5', $Item->getAmountOpenFormatted());
        self::assertSame('amount:84.03', $Item->getAmountTotalNetFormatted());
        self::assertSame('amount:15.97', $Item->getAmountTotalVatFormatted());
        self::assertSame('amount:100', $Item->getAmountTotalSumFormatted());
    }

    public function testOptionalPropertiesExposeTheirDefaultsAndCanBeCleared(): void
    {
        $Item = new Item(7, 'order');

        self::assertSame('', $Item->getTitle());
        self::assertSame('', $Item->getDescription());
        self::assertSame('', $Item->getDocumentNo());
        self::assertFalse($Item->getLastPaymentDate());
        self::assertSame('-', $Item->getLastPaymentDateFormatted());
        self::assertFalse($Item->getDunningLevel());
        self::assertNull($Item->getGlobalProcessId());
        self::assertNull($Item->getHash());

        $Item->setGlobalProcessId('temporary');
        $Item->setHash('temporary');
        $Item->setGlobalProcessId(null);
        $Item->setHash(null);

        self::assertNull($Item->getGlobalProcessId());
        self::assertNull($Item->getHash());
    }
}
