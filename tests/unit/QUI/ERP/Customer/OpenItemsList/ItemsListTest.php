<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\OpenItemsList;

use DateTime;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Currency\Currency;

final class ItemsListTest extends TestCase
{
    public function testItemsAreSortedFilteredAndSummedByCurrency(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Currency->method('format')
            ->willReturnCallback(static fn (float|int $amount): string => number_format((float)$amount, 2));

        $LaterItem = $this->item($Currency, '2026-08-12', 10, 1.9, 11.9, 3.9, 8);
        $EarlierItem = $this->item($Currency, '2026-08-10', 20, 3.8, 23.8, 10, 13.8);

        $List = new ItemsList();
        $List->addItem($LaterItem);
        $List->addItem($EarlierItem);

        self::assertSame([$EarlierItem, $LaterItem], $List->getItems());
        self::assertSame([$EarlierItem, $LaterItem], $List->getItemsByCurrencyCode('EUR'));
        self::assertSame([], $List->getItemsByCurrencyCode('USD'));
        $totals = $List->getTotalAmountsByCurrency()['EUR'];
        self::assertEqualsWithDelta(30, $totals['netTotal'], 0.001);
        self::assertSame('30.00', $totals['netTotalFormatted']);
        self::assertEqualsWithDelta(5.7, $totals['vatTotal'], 0.001);
        self::assertSame('5.70', $totals['vatTotalFormatted']);
        self::assertEqualsWithDelta(35.7, $totals['sumTotal'], 0.001);
        self::assertSame('35.70', $totals['sumTotalFormatted']);
        self::assertEqualsWithDelta(21.8, $totals['dueTotal'], 0.001);
        self::assertSame('21.80', $totals['dueTotalFormatted']);
        self::assertEqualsWithDelta(13.9, $totals['paidTotal'], 0.001);
        self::assertSame('13.90', $totals['paidTotalFormatted']);
    }

    public function testDateUsesStableFallbackWithoutAssignedUser(): void
    {
        $List = new ItemsList();
        $Date = new DateTime('2026-08-13 09:15:00');
        $List->setDate($Date);

        self::assertSame($Date, $List->getDate());
        self::assertSame('2026-08-13 09:15', $List->getDateFormatted());
    }

    public function testAssignedUserLocaleAndHtmlOutputAreAvailable(): void
    {
        $Locale = $this->createMock(\QUI\Locale::class);
        $Locale->method('formatDate')->willReturn('localized date');
        $User = $this->createMock(\QUI\Users\User::class);
        $User->method('getLocale')->willReturn($Locale);
        $List = new ItemsList();
        $List->setDate(new DateTime('2026-08-13 09:15:00'));
        $List->setUser($User);

        self::assertSame($User, $List->getUser());
        self::assertSame('localized date', $List->getDateFormatted());
        self::assertStringContainsString('<style>', $List->getListAsHtmlTable());
    }

    public function testItemsWithIdenticalDatesRemainInList(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Currency->method('format')->willReturn('0.00');
        $First = $this->item($Currency, '2026-08-13', 0, 0, 0, 0, 0);
        $Second = $this->item($Currency, '2026-08-13', 0, 0, 0, 0, 0);
        $List = new ItemsList();

        $List->addItem($First);
        $List->addItem($Second);

        self::assertCount(2, $List->getItems());
    }

    private function item(
        Currency $Currency,
        string $date,
        float $net,
        float $vat,
        float $sum,
        float $paid,
        float $open
    ): Item {
        $Item = new Item($date, 'invoice');
        $Item->setCurrency($Currency);
        $Item->setDate(new DateTime($date));
        $Item->setAmountTotalNet($net);
        $Item->setAmountTotalVat($vat);
        $Item->setAmountTotalSum($sum);
        $Item->setAmountPaid($paid);
        $Item->setAmountOpen($open);

        return $Item;
    }
}
