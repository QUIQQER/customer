<?php

declare(strict_types=1);

namespace QUI\ERP\Customer;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;

final class SearchTest extends TestCase
{
    public function testClearFilterRemovesFieldFiltersAndSearchTerm(): void
    {
        $Search = new Search();
        $Search->setFilter('company', 'Example GmbH');
        $Search->setFilter('search', 'Müller');

        $Search->clearFilter();

        self::assertSame([], $this->property($Search, 'filter'));
        self::assertSame('', $this->property($Search, 'search'));
    }

    /**
     * @dataProvider customerNumberSearchProvider
     */
    public function testCustomerNumberPrefixIsRemovedOnlyAtTheStart(
        string $search,
        string $prefix,
        string $expected
    ): void {
        $Method = new ReflectionMethod(Search::class, 'removeCustomerNoPrefix');

        self::assertSame($expected, $Method->invoke(null, $search, $prefix));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function customerNumberSearchProvider(): array
    {
        return [
            'prefixed customer number' => ['KD-123', 'KD-', '123'],
            'case-insensitive prefix' => ['kd-123', 'KD-', '123'],
            'customer number without prefix' => ['123', 'KD-', '123'],
            'prefix in the middle' => ['123-KD-456', 'KD-', '123-KD-456'],
            'empty prefix' => ['123', '', '123']
        ];
    }

    public function testTablesAndAllowedFieldsUseQuiqqerSchema(): void
    {
        $Search = new Search();

        self::assertSame(\QUI::getDBTableName('users'), $Search->table());
        self::assertSame(\QUI::getDBTableName('users_address'), $Search->tableAddress());
        self::assertContains('customerId', $Search->getAllowedFields());
        self::assertContains('company', $Search->getAllowedFields());
    }

    #[DataProvider('orderProvider')]
    public function testOrderAcceptsOnlyMappedColumns(
        string $column,
        string $direction,
        string $expected
    ): void {
        $Search = new Search();
        $Search->order($column, $direction);

        self::assertSame($expected, $this->property($Search, 'order'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function orderProvider(): array
    {
        return [
            'id' => ['id', 'ASC', 'user_id ASC'],
            'email desc' => ['email', 'desc', 'users.`email` DESC'],
            'firstname' => ['firstname', 'ASC', 'users.`firstname` ASC, ad.`firstname` ASC'],
            'lastname' => ['lastname', 'invalid', 'users.`lastname` DESC, ad.`lastname` DESC'],
            'company' => ['company', 'ASC', 'ad.`company` ASC'],
            'invalid keeps default' => ['malicious', 'ASC', 'user_id DESC']
        ];
    }

    public function testLimitAndSearchScopeUpdateQueryState(): void
    {
        $Search = new Search();
        $Search->limit('10', '5');
        $Search->searchInAllGroups();

        self::assertSame([10, 5], $this->property($Search, 'limit'));
        self::assertFalse($this->property($Search, 'onlyCustomer'));

        $Search->searchOnlyInCustomer();
        self::assertTrue($this->property($Search, 'onlyCustomer'));
    }

    public function testFilterRoutingIgnoresUnknownAndInvalidSearchValues(): void
    {
        $Search = new Search();
        $Search->setFilter('userId', ['first', 'second']);
        $Search->setFilter('group', 'customer-group');
        $Search->setFilter('regdate_from', '2026-01-01');
        $Search->setFilter('email', 'mail@example.invalid');
        $Search->setFilter('unknown', 'ignored');
        $Search->setFilter('search', ['invalid']);

        self::assertSame([
            'userId' => ['first', 'second'],
            'usergroup' => 'customer-group',
            'regdate_from' => '2026-01-01',
            'email' => 'mail@example.invalid'
        ], $this->property($Search, 'filter'));
        self::assertSame('', $this->property($Search, 'search'));
    }

    public function testQueryContainsBoundSearchTermsFiltersSortAndPagination(): void
    {
        $Search = new Search();
        $Search->searchInAllGroups();
        $Search->limit(5, 10);
        $Search->order('company', 'DESC');
        $Search->setFilter('search', 'Alice Example');
        $Search->setFilter('regdate_from', '2026-01-01');
        $Search->setFilter('regdate_to', '2026-12-31');

        $query = (new ReflectionMethod(Search::class, 'getQuery'))->invoke($Search);
        $countQuery = (new ReflectionMethod(Search::class, 'getQueryCount'))->invoke($Search);

        self::assertStringContainsString('LEFT JOIN', $query['query']);
        self::assertStringContainsString('ORDER BY ad.`company` DESC', $query['query']);
        self::assertStringContainsString('LIMIT 10 OFFSET 5', $query['query']);
        self::assertStringContainsString('users.regdate >= :filter0', $query['query']);
        self::assertStringContainsString('users.regdate <= :filter1', $query['query']);
        self::assertSame('%Alice Example%', $query['binds']['search']['value']);
        self::assertSame('%Alice%', $query['binds']['searchSplit0']['value']);
        self::assertSame('%Example%', $query['binds']['searchSplit1']['value']);
        self::assertStringContainsString('SELECT COUNT', $countQuery['query']);
    }

    public function testEmptyUnrestrictedQueryDoesNotAddWhereClause(): void
    {
        $Search = new Search();
        $Search->searchInAllGroups();
        $query = (new ReflectionMethod(Search::class, 'getQuery'))->invoke($Search);
        $countQuery = (new ReflectionMethod(Search::class, 'getQueryCount'))->invoke($Search);

        self::assertStringNotContainsString('WHERE', $query['query']);
        self::assertStringContainsString('SELECT COUNT', $countQuery['query']);
        self::assertSame([], $query['binds']);
        self::assertSame([], $countQuery['binds']);
    }

    private function property(Search $Search, string $name): mixed
    {
        return (new ReflectionProperty($Search, $name))->getValue($Search);
    }
}
