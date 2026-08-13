<?php

namespace QUI\ERP\Customer\OpenItemsList;

use DateTime;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\Order;
use ReflectionMethod;

class HandlerTest extends TestCase
{
    public function testCreateDateTimeReturnsParsedDate(): void
    {
        $Method = new ReflectionMethod(Handler::class, 'createDateTime');
        $Method->setAccessible(true);

        $Date = $Method->invoke(null, '2026-07-08 15:45:00');

        $this->assertInstanceOf(DateTime::class, $Date);
        $this->assertSame('2026-07-08 15:45:00', $Date->format('Y-m-d H:i:s'));
    }

    public function testCreateDateTimeFallsBackToNowForNonScalarInput(): void
    {
        $Method = new ReflectionMethod(Handler::class, 'createDateTime');
        $Method->setAccessible(true);

        $before = time();
        $Date = $Method->invoke(null, ['not scalar']);
        $after = time();

        $this->assertInstanceOf(DateTime::class, $Date);
        $this->assertGreaterThanOrEqual($before, $Date->getTimestamp());
        $this->assertLessThanOrEqual($after, $Date->getTimestamp());
    }

    public function testResolveOpenItemsSortMapsDisplayColumnAndDirection(): void
    {
        $Method = new ReflectionMethod(Handler::class, 'resolveOpenItemsSort');
        $Method->setAccessible(true);

        $this->assertSame(
            ['column' => 'open_sum', 'direction' => 'DESC'],
            $Method->invoke(null, ['sortOn' => 'display_open_sum', 'sortBy' => 'DESC'])
        );
    }

    public function testResolveOpenItemsSortRejectsUnknownColumnAndDirection(): void
    {
        $Method = new ReflectionMethod(Handler::class, 'resolveOpenItemsSort');
        $Method->setAccessible(true);

        $this->assertSame(
            ['column' => 'userId', 'direction' => 'ASC'],
            $Method->invoke(null, ['sortOn' => 'malicious SQL', 'sortBy' => 'malicious SQL'])
        );
    }

    public function testTemporaryInvoiceIsConvertedToOpenItem(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Invoice = $this->createMock(InvoiceTemporary::class);
        $Invoice->method('getId')->willReturn(42);
        $Invoice->method('getPrefixedNumber')->willReturn('INV-42');
        $Invoice->method('getGlobalProcessId')->willReturn('process-42');
        $Invoice->method('getUUID')->willReturn('invoice-uuid-42');
        $Invoice->method('getAttribute')->willReturnMap([
            ['c_date', '2026-08-01 10:00:00'],
            ['time_for_payment', '2026-08-10 10:00:00'],
            ['nettosum', 100.0],
            ['sum', 119.0],
            ['vat_array', json_encode([
                ['sum' => 10.0],
                ['sum' => 9.0]
            ])]
        ]);
        $Invoice->method('getPaidStatusInformation')->willReturn([
            'paid' => 20.0,
            'toPay' => 99.0
        ]);
        $Invoice->method('getCurrency')->willReturn($Currency);
        $Method = new ReflectionMethod(Handler::class, 'parseInvoiceToOpenItem');

        $Item = $Method->invoke(null, $Invoice);

        self::assertSame(42, $Item->getDocumentId());
        self::assertSame(Handler::DOCUMENT_TYPE_INVOICE, $Item->getDocumentType());
        self::assertSame('INV-42', $Item->getDocumentNo());
        self::assertSame('process-42', $Item->getGlobalProcessId());
        self::assertSame('invoice-uuid-42', $Item->getHash());
        self::assertSame('2026-08-01 10:00:00', $Item->getDate()->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-10 10:00:00', $Item->getDueDate()->format('Y-m-d H:i:s'));
        self::assertSame(20.0, $Item->getAmountPaid());
        self::assertSame(99.0, $Item->getAmountOpen());
        self::assertSame(100.0, $Item->getAmountTotalNet());
        self::assertSame(119.0, $Item->getAmountTotalSum());
        self::assertSame(19.0, $Item->getAmountTotalVat());
        self::assertSame($Currency, $Item->getCurrency());
        self::assertGreaterThan(0, $Item->getDaysDue());
        self::assertFalse($Item->getLastPaymentDate());
    }

    public function testOrderIsConvertedToOpenItem(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Articles = $this->createMock(\QUI\ERP\Accounting\ArticleList::class);
        $Articles->method('getCalculations')->willReturn([
            'nettoSum' => 200.0,
            'sum' => 238.0,
            'vatArray' => [
                ['sum' => 38.0]
            ]
        ]);
        $Order = $this->createMock(Order::class);
        $Order->method('getCleanId')->willReturn(84);
        $Order->method('getPrefixedNumber')->willReturn('ORD-84');
        $Order->method('getGlobalProcessId')->willReturn('process-84');
        $Order->method('getUUID')->willReturn('order-uuid-84');
        $Order->method('getAttribute')->willReturnMap([
            ['c_date', '2026-08-02 11:00:00'],
            ['payment_time', '2026-08-20 11:00:00']
        ]);
        $Order->method('getPaidStatusInformation')->willReturn([
            'paid' => 40.0,
            'toPay' => 198.0
        ]);
        $Order->method('getArticles')->willReturn($Articles);
        $Order->method('getCurrency')->willReturn($Currency);
        $Method = new ReflectionMethod(Handler::class, 'parseOrderToOpenItem');

        $Item = $Method->invoke(null, $Order);

        self::assertSame(84, $Item->getDocumentId());
        self::assertSame(Handler::DOCUMENT_TYPE_ORDER, $Item->getDocumentType());
        self::assertSame('ORD-84', $Item->getDocumentNo());
        self::assertSame('process-84', $Item->getGlobalProcessId());
        self::assertSame('order-uuid-84', $Item->getHash());
        self::assertSame(40.0, $Item->getAmountPaid());
        self::assertSame(198.0, $Item->getAmountOpen());
        self::assertSame(200.0, $Item->getAmountTotalNet());
        self::assertSame(238.0, $Item->getAmountTotalSum());
        self::assertSame(38.0, $Item->getAmountTotalVat());
        self::assertSame($Currency, $Item->getCurrency());
    }
}
