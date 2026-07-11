<?php

namespace QUI\ERP\Customer\OpenItemsList;

use DateTime;
use PHPUnit\Framework\TestCase;
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
}
