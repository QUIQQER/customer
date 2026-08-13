<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Customer\OpenItemsList\Handler;
use QUI\ERP\Customer\OpenItemsList\Item;
use QUI\ERP\Customer\OpenItemsList\ItemsList;
use QUI\Interfaces\Users\User;

final class OpenItemsHandlerDatabaseTest extends TestCase
{
    private string $userUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userUuid = 'phpunit-open-items-' . bin2hex(random_bytes(6));
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testIdenticalSnapshotCanBeSynchronizedTwice(): void
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn($this->userUuid);
        $User->method('getAttribute')
            ->with('customerId')
            ->willReturn('C-42');
        $List = $this->createOpenItemsList();

        Handler::syncOpenItemsRecord($User, $List);
        Handler::syncOpenItemsRecord($User, $List);

        self::assertSame(1, Handler::searchOpenItems([
            'count' => true,
            'userId' => $this->userUuid
        ]));
        $rows = Handler::searchOpenItems(['userId' => $this->userUuid]);
        self::assertCount(1, $rows);
        self::assertSame('EUR', $rows[0]['currency']);
        self::assertEqualsWithDelta(119, (float)$rows[0]['total_sum'], 0.001);
    }

    public function testSynchronizationRemovesCurrenciesMissingFromCurrentSnapshot(): void
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn($this->userUuid);
        $User->method('getAttribute')->willReturn('C-42');

        Handler::syncOpenItemsRecord($User, $this->createOpenItemsList(['EUR', 'USD']));
        self::assertSame(2, Handler::searchOpenItems([
            'count' => true,
            'userId' => $this->userUuid
        ]));

        Handler::syncOpenItemsRecord($User, $this->createOpenItemsList(['EUR']));

        $rows = Handler::searchOpenItems(['userId' => $this->userUuid]);
        self::assertCount(1, $rows);
        self::assertSame('EUR', $rows[0]['currency']);
    }

    public function testSearchSupportsFiltersSortingPaginationAndTotals(): void
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn($this->userUuid);
        $User->method('getAttribute')->willReturn('C-42');
        Handler::syncOpenItemsRecord($User, $this->createOpenItemsList(['EUR', 'USD']));

        $eurRows = Handler::searchOpenItems([
            'search' => 'C-42',
            'currency' => 'EUR',
            'sortOn' => 'display_open_sum',
            'sortBy' => 'DESC',
            'page' => 1,
            'perPage' => 1
        ]);
        self::assertCount(1, $eurRows);
        self::assertSame('EUR', $eurRows[0]['currency']);

        $pagedRows = Handler::searchOpenItems([
            'userId' => $this->userUuid,
            'sortOn' => 'currency_code',
            'sortBy' => 'ASC',
            'page' => 2,
            'perPage' => 1
        ]);
        self::assertCount(1, $pagedRows);
        self::assertSame('USD', $pagedRows[0]['currency']);

        $Currency = $this->createMock(Currency::class);
        $Currency->method('format')
            ->willReturnCallback(static fn (float|int $amount): string => 'formatted:' . $amount);
        self::assertSame([
            'display_net' => 'formatted:200',
            'display_vat' => 'formatted:38',
            'display_gross' => 'formatted:238',
            'display_paid' => 'formatted:40',
            'display_open' => 'formatted:198'
        ], Handler::getTotals(Handler::searchOpenItems(['userId' => $this->userUuid]), $Currency));
    }

    public function testEmptySnapshotDeletesPersistedOpenItems(): void
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn($this->userUuid);
        $User->method('getAttribute')->willReturn('C-42');
        Handler::syncOpenItemsRecord($User, $this->createOpenItemsList());

        Handler::syncOpenItemsRecord($User, new ItemsList());

        self::assertSame(0, Handler::searchOpenItems([
            'count' => true,
            'userId' => $this->userUuid
        ]));
    }

    /**
     * @param list<string> $currencyCodes
     */
    private function createOpenItemsList(array $currencyCodes = ['EUR']): ItemsList
    {
        $List = new ItemsList();

        foreach ($currencyCodes as $currencyCode) {
            $Currency = $this->createMock(Currency::class);
            $Currency->method('getCode')->willReturn($currencyCode);
            $Currency->method('format')
                ->willReturnCallback(static fn (float|int $amount): string => (string)$amount);

            $Item = new Item(42, Handler::DOCUMENT_TYPE_INVOICE);
            $Item->setCurrency($Currency);
            $Item->setDate(new \DateTime('2026-08-13'));
            $Item->setAmountTotalNet(100);
            $Item->setAmountTotalVat(19);
            $Item->setAmountTotalSum(119);
            $Item->setAmountPaid(20);
            $Item->setAmountOpen(99);
            $List->addItem($Item);
        }

        return $List;
    }

    private function cleanup(): void
    {
        QUI::getDataBaseConnection()->delete(Handler::getTable(), [
            'userId' => $this->userUuid
        ]);
    }
}
