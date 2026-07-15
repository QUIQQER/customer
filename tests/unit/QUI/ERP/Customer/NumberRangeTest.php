<?php

namespace QUI\ERP\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NumberRangeTest extends TestCase
{
    /**
     * @param list<mixed> $customerIds
     */
    #[DataProvider('customerIdProvider')]
    public function testDetermineNextRangeUsesHighestNumericSuffix(array $customerIds, int $expected): void
    {
        $Method = new ReflectionMethod(NumberRange::class, 'determineNextRange');
        $Method->setAccessible(true);

        $this->assertSame($expected, $Method->invoke(null, $customerIds));
    }

    /**
     * @return array<string, array{list<mixed>, int}>
     */
    public static function customerIdProvider(): array
    {
        return [
            'empty input' => [[], 1],
            'prefixed values' => [['K100', 'K9', 'customer-42'], 101],
            'ignores values without numeric suffix' => [['K17-test', null, 'customer'], 1],
            'accepts numeric values' => [[5, 27, 3], 28]
        ];
    }
}
