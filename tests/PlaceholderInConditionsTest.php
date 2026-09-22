<?php
declare(strict_types=1);

namespace ParagonIE\EasyDB\Tests;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyPlaceholder;
use ParagonIE\EasyDB\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Upstream accepts an EasyPlaceholder only among the values being written
 * (INSERT values, UPDATE SET). Here it also works in the conditions of
 * delete() and update(), and in the read-back of insertGet().
 */
#[CoversClass(EasyDB::class)]
#[CoversClass(EasyPlaceholder::class)]
#[CoversClass(Factory::class)]
class PlaceholderInConditionsTest extends EasyDBWriteTestCase
{
    private function seeded(callable $cb): EasyDB
    {
        $db = $this->easyDBExpectedFromCallable($cb);
        $db->run('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT, s TEXT)');
        $db->insertMany('t', [
            ['id' => 1, 'v' => 'A',  's' => 'one'],
            ['id' => 2, 'v' => 'B',  's' => 'two'],
            ['id' => 3, 'v' => 'ab', 's' => 'three'],
            ['id' => 4, 'v' => 'ab', 's' => 'four'],
        ]);
        return $db;
    }

    private function ids(EasyDB $db): array
    {
        return array_map('intval', $db->column('SELECT id FROM t ORDER BY id'));
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testDeleteWithPlaceholderCondition(callable $cb): void
    {
        $db = $this->seeded($cb);
        $n = $db->delete('t', ['v' => new EasyPlaceholder('UPPER(?)', 'b')]);
        $this->assertSame(1, $n);
        $this->assertSame([1, 3, 4], $this->ids($db));
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testUpdateWithPlaceholderCondition(callable $cb): void
    {
        $db = $this->seeded($cb);
        $n = $db->update('t', ['s' => 'changed'], ['v' => new EasyPlaceholder('UPPER(?)', 'a')]);
        $this->assertSame(1, $n);
        $this->assertSame('changed', $db->cell('SELECT s FROM t WHERE id = ?', 1));
        $this->assertSame('two', $db->cell('SELECT s FROM t WHERE id = ?', 2));
    }

    /**
     * A placeholder with two parameters between two plain conditions: the
     * parameters must be bound in the same order as they appear in the SQL,
     * or the statement silently hits another row.
     *
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testParameterOrderWithMixedConditions(callable $cb): void
    {
        $db = $this->seeded($cb);
        $n = $db->delete('t', [
            'id' => 4,
            'v'  => new EasyPlaceholder('? || ?', 'a', 'b'),
            's'  => 'four',
        ]);
        $this->assertSame(1, $n);
        $this->assertSame([1, 2, 3], $this->ids($db));

        $n = $db->update('t', ['s' => 'x'], [
            'id' => 3,
            'v'  => new EasyPlaceholder('? || ?', 'a', 'b'),
            's'  => 'three',
        ]);
        $this->assertSame(1, $n);
        $this->assertSame('x', $db->cell('SELECT s FROM t WHERE id = ?', 3));
    }

    /**
     * On SQLite insertGet() reads the row back (no RETURNING path), so the
     * placeholder ends up in the WHERE of the read-back query.
     *
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testInsertGetReadBackWithPlaceholder(callable $cb): void
    {
        $db = $this->seeded($cb);
        $s = $db->insertGet('t', ['id' => 5, 'v' => new EasyPlaceholder('UPPER(?)', 'z'), 's' => 'five'], 's');
        $this->assertSame('five', $s);
        $this->assertSame('Z', $db->cell('SELECT v FROM t WHERE id = ?', 5));
    }
}
