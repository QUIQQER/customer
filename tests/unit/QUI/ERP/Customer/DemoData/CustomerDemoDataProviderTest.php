<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\DemoData;

use PHPUnit\Framework\TestCase;
use QUI\Locale;

final class CustomerDemoDataProviderTest extends TestCase
{
    public function testReturnsLocalizedTitle(): void
    {
        $locale = $this->createMock(Locale::class);
        $locale->expects(self::once())
            ->method('get')
            ->with('quiqqer/customer', 'package.title')
            ->willReturn('Customers');

        self::assertSame('Customers', (new CustomerDemoDataProvider())->getTitle($locale));
    }
}
