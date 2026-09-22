<?php
declare(strict_types=1);

namespace ParagonIE\EasyDB\Tests;

use Generator;
use InvalidArgumentException;
use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\Exception\MustBeOneDimensionalArray;
use ParagonIE\EasyDB\Factory;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * columnGenerator() and safeQueryGenerator() must yield exactly what column()
 * and safeQuery() return, just one row at a time.
 */
#[CoversClass(EasyDB::class)]
#[CoversClass(Factory::class)]
#[CoversClass(MustBeOneDimensionalArray::class)]
class GeneratorTest extends EasyDBWriteTestCase
{
    private function seeded(callable $cb): EasyDB
    {
        $db = $this->easyDBExpectedFromCallable($cb);
        $db->run('CREATE TABLE t (id INTEGER PRIMARY KEY, v INTEGER, s TEXT)');
        // falsy values on purpose: 0, the empty string and NULL must not end the iteration
        $db->insertMany('t', [
            ['id' => 1, 'v' => 0,    's' => ''],
            ['id' => 2, 'v' => null, 's' => 'x'],
            ['id' => 3, 'v' => 7,    's' => '0'],
        ]);
        return $db;
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testColumnGeneratorMatchesColumn(callable $cb): void
    {
        $db = $this->seeded($cb);
        foreach ([0, 1, 2] as $offset) {
            $gen = $db->columnGenerator('SELECT id, v, s FROM t ORDER BY id', [], $offset);
            $this->assertInstanceOf(Generator::class, $gen);
            $this->assertSame(
                $db->column('SELECT id, v, s FROM t ORDER BY id', [], $offset),
                iterator_to_array($gen, false)
            );
        }
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testSafeQueryGeneratorMatchesSafeQuery(callable $cb): void
    {
        $db = $this->seeded($cb);
        $sql = 'SELECT id, v, s FROM t WHERE id > ? ORDER BY id';
        $styles = [
            PDO::FETCH_ASSOC,
            PDO::FETCH_NUM,
            PDO::FETCH_BOTH,
            PDO::FETCH_COLUMN,
            EasyDB::DEFAULT_FETCH_STYLE,
        ];
        foreach ($styles as $style) {
            $this->assertSame(
                $db->safeQuery($sql, [0], $style),
                iterator_to_array($db->safeQueryGenerator($sql, [0], $style), false),
                "fetch style {$style}"
            );
        }
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testFetchColumnDoesNotStopOnFalsyValues(callable $cb): void
    {
        $db = $this->seeded($cb);
        $this->assertSame(
            [0, null, 7],
            iterator_to_array(
                $db->safeQueryGenerator('SELECT v FROM t ORDER BY id', [], PDO::FETCH_COLUMN),
                false
            )
        );
    }

    /**
     * The query must run when the method is called, not on the first
     * iteration: callers wrap the call in their own error handling.
     *
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testQueryErrorsSurfaceBeforeIterating(callable $cb): void
    {
        $db = $this->seeded($cb);
        foreach (['safeQueryGenerator', 'columnGenerator'] as $method) {
            try {
                $db->$method('SELECT * FROM table_that_does_not_exist');
                $this->fail("{$method}() must throw before being iterated");
            } catch (PDOException $e) {
                $this->assertInstanceOf(PDOException::class, $e);
            }
        }
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testParamsAreValidatedBeforeIterating(callable $cb): void
    {
        $db = $this->seeded($cb);
        foreach (['safeQueryGenerator', 'columnGenerator'] as $method) {
            try {
                $db->$method('SELECT v FROM t WHERE id = ?', [[1]]);
                $this->fail("{$method}() must reject a two-dimensional array");
            } catch (MustBeOneDimensionalArray $e) {
                $this->assertInstanceOf(MustBeOneDimensionalArray::class, $e);
            }
        }
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testInvalidColumnOffsetThrows(callable $cb): void
    {
        $db = $this->seeded($cb);
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array($db->columnGenerator('SELECT id FROM t', [], 5));
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testEmptyResultYieldsNothing(callable $cb): void
    {
        $db = $this->seeded($cb);
        $this->assertSame([], iterator_to_array($db->safeQueryGenerator('SELECT * FROM t WHERE id > ?', [99]), false));
        $this->assertSame([], iterator_to_array($db->columnGenerator('SELECT id FROM t WHERE id > ?', [99]), false));
    }
}
