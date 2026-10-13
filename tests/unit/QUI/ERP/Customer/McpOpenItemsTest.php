<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Unit;

use DateTime;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Customer\MCP\OpenItemsTools;
use QUI\ERP\Customer\OpenItemsList\Item;
use QUI\ERP\Customer\OpenItemsList\ItemsList;

final class McpOpenItemsTest extends TestCase
{
    public function testTotalsRemainPerCurrencyAndIncludeEveryMatchingPage(): void
    {
        $List = $this->list();
        $result = OpenItemsTools::format($List, '', '', false, 1, 0);
        self::assertSame(3, $result['total']);
        self::assertCount(1, $result['items']);
        self::assertSame('INV-USD', $result['items'][0]['documentNo']);
        self::assertSame(20.0, $result['totalsByCurrency']['EUR']['open']);
        self::assertSame(10.0, $result['totalsByCurrency']['USD']['open']);
        self::assertSame(30.0, $result['totalsByCurrency']['EUR']['gross']);
        $eur = OpenItemsTools::format($List, 'EUR', '', false, 20, 0);
        self::assertSame(2, $eur['total']);
        self::assertSame(['EUR'], array_keys($eur['totalsByCurrency']));
        $lastPage = OpenItemsTools::format($List, '', '', false, 1, 3);
        self::assertSame([], $lastPage['items']);
        self::assertSame($result['totalsByCurrency'], $lastPage['totalsByCurrency']);
    }

    public function testDocumentAndOverdueFiltersAffectItemsAndTotalsTogether(): void
    {
        $List = $this->list();
        $overdue = OpenItemsTools::format($List, '', '', true, 20, 0);
        self::assertSame(2, $overdue['total']);
        self::assertSame(10.0, $overdue['totalsByCurrency']['EUR']['open']);
        $search = OpenItemsTools::format($List, '', 'inv-eur-1', false, 20, 0);
        self::assertSame(1, $search['total']);
        self::assertSame('INV-EUR-1', $search['items'][0]['documentNo']);
        $empty = OpenItemsTools::format($List, '', 'missing', false, 20, 0);
        self::assertSame(0, $empty['total']);
        self::assertSame([], $empty['totalsByCurrency']);
    }

    private function list(): ItemsList
    {
        $List = new ItemsList();
        $List->setDate(new DateTime('2026-10-13'));
        $fixtures = [
            ['EUR', 'INV-EUR-1', '2026-10-01', 0],
            ['EUR', 'INV-EUR-2', '2026-10-02', 10],
            ['USD', 'INV-USD', '2026-10-03', 5]
        ];

        foreach ($fixtures as [$code, $number, $date, $daysDue]) {
            $Currency = $this->createMock(Currency::class);
            $Currency->method('getCode')->willReturn($code);
            $Currency->method('format')->willReturn('formatted');
            $Item = new Item($number, 'invoice');
            $Item->setDocumentNo($number);
            $Item->setCurrency($Currency);
            $Item->setDate(new DateTime($date));
            $Item->setDueDate(new DateTime($date));
            $Item->setDaysDue($daysDue);
            $Item->setAmountTotalNet(12);
            $Item->setAmountTotalVat(3);
            $Item->setAmountTotalSum(15);
            $Item->setAmountPaid(5);
            $Item->setAmountOpen(10);
            $List->addItem($Item);
        }

        return $List;
    }
}
