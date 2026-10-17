<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderSearchTest extends TestCase
{
    public function testSingleTermOrsAcrossColumns(): void
    {
        $bindings = [];
        $sql = QueryBuilder::applySearch(
            'SELECT x FROM t',
            ['t.first_name', 't.last_name'],
            $bindings,
            'john'
        );

        $this->assertStringContainsString('t.first_name LIKE :srch_t0_c0', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertSame('%john%', $bindings[':srch_t0_c0']);
        $this->assertSame('%john%', $bindings[':srch_t0_c1']);
    }

    public function testMultipleTermsAreAnded(): void
    {
        $bindings = [];
        $sql = QueryBuilder::applySearch(
            'SELECT x FROM t',
            ['t.name'],
            $bindings,
            '  john   doe  '
        );

        $this->assertStringContainsString(':srch_t0_c0', $sql);
        $this->assertStringContainsString(':srch_t1_c0', $sql);
        $this->assertStringContainsString(' AND ', $sql);
        $this->assertCount(2, $bindings);
    }

    public function testEmptySearchLeavesSqlUntouched(): void
    {
        $bindings = [];

        $this->assertSame(
            'SELECT x FROM t',
            QueryBuilder::applySearch('SELECT x FROM t', ['t.name'], $bindings, null)
        );
        $this->assertSame([], $bindings);
    }

    public function testNoSearchableColumnsLeavesSqlUntouched(): void
    {
        $bindings = [];

        $this->assertSame(
            'SELECT x FROM t',
            QueryBuilder::applySearch('SELECT x FROM t', [], $bindings, 'john')
        );
    }

    public function testCustomParamPrefixAvoidsCollisions(): void
    {
        $bindings = [];
        $sql = QueryBuilder::applySearch(
            'SELECT x FROM t',
            ['t.name'],
            $bindings,
            'john',
            'v'
        );

        $this->assertStringContainsString(':v_t0_c0', $sql);
    }
}
