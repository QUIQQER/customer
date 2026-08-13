<?php

declare(strict_types=1);

namespace QUI\ERP\Customer;

use PHPUnit\Framework\TestCase;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Customer\DemoData\CustomerDemoDataCreator;
use QUI\ERP\Customer\DemoData\CustomerDemoDataProvider;

final class ProviderTest extends TestCase
{
    public function testBackendSearchMetadataIsStable(): void
    {
        $Provider = new BackendSearchProvider();
        $Provider->buildCache();

        $entry = $Provider->getEntry('customer-42');
        $searchData = json_decode($entry['searchdata'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            'package/quiqqer/customer/bin/backend/controls/customer/Panel',
            $searchData['require']
        );
        self::assertSame('customer-42', $searchData['params']['userId']);
        self::assertSame('customers', $Provider->getFilterGroups()[0]['group']);
        self::assertSame([], $Provider->search('anything', ['filterGroups' => ['other']]));
    }

    public function testErpProviderExposesCustomerNumberRange(): void
    {
        $ranges = ErpProvider::getNumberRanges();

        self::assertCount(1, $ranges);
        self::assertInstanceOf(NumberRange::class, $ranges[0]);
    }

    public function testErpProviderAddsAccountingMenuAndOpenItemsEntry(): void
    {
        $Map = new Map();
        ErpProvider::addMenuItems($Map);

        $Accounting = $Map->getChildrenByName('accounting');
        self::assertInstanceOf(Item::class, $Accounting);
        self::assertSame('open_items', $Accounting->toArray()['items'][0]['name']);

        $ExistingMap = new Map();
        $ExistingAccounting = new Item(['name' => 'accounting']);
        $ExistingMap->appendChild($ExistingAccounting);
        ErpProvider::addMenuItems($ExistingMap);

        self::assertSame($ExistingAccounting, $ExistingMap->getChildrenByName('accounting'));
        self::assertSame('open_items', $ExistingAccounting->toArray()['items'][0]['name']);
    }

    public function testDemoDataProviderCreatesExpectedCreator(): void
    {
        $Provider = new CustomerDemoDataProvider();

        self::assertSame('quiqqer.customer', $Provider->getIdentifier());
        self::assertNotSame('', $Provider->getTitle());
        self::assertInstanceOf(
            CustomerDemoDataCreator::class,
            $Provider->getDemoDataCreator(\QUI::getDataBaseConnection())
        );
    }
}
