<?php
declare(strict_types=1);

namespace ParagonIE\EasyDB\Tests;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * single() must tell "no rows" apart from a falsy value, and must never answer
 * false: applications commonly use false to signal a failed query.
 */
#[CoversClass(EasyDB::class)]
#[CoversClass(Factory::class)]
class SingleNoRowsTest extends EasyDBWriteTestCase
{
    /**
     * Runs $fn turning any PHP warning into a failure. PHPUnit's own
     * failOnWarning does not make the run fail on a PHP warning, and the 2.x
     * implementation of single() emitted one on every empty result.
     */
    private function withoutWarnings(callable $fn): mixed
    {
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new \ErrorException($errstr, 0, $errno);
        });
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    private function seeded(callable $cb): EasyDB
    {
        $db = $this->easyDBExpectedFromCallable($cb);
        $db->run('CREATE TABLE t (id INTEGER PRIMARY KEY, v INTEGER)');
        $db->insertMany('t', [['id' => 1, 'v' => 0], ['id' => 2, 'v' => 42], ['id' => 3, 'v' => null]]);
        return $db;
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testNoRowsReturnsNull(callable $cb): void
    {
        $db = $this->seeded($cb);
        $this->assertNull($this->withoutWarnings(fn() => $db->single('SELECT v FROM t WHERE id = ?', [999])));
        $this->assertNull($this->withoutWarnings(fn() => $db->cell('SELECT v FROM t WHERE id = ?', 999)));
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testFalsyValueIsNotMistakenForNoRows(callable $cb): void
    {
        $db = $this->seeded($cb);
        $this->assertSame(0, $db->single('SELECT v FROM t WHERE id = ?', [1]));
        $this->assertSame(42, $db->single('SELECT v FROM t WHERE id = ?', [2]));
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testSqlNullValueIsNull(callable $cb): void
    {
        $db = $this->seeded($cb);
        $this->assertNull($db->single('SELECT v FROM t WHERE id = ?', [3]));
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testExistsIsUnchanged(callable $cb): void
    {
        $db = $this->seeded($cb);
        $this->assertFalse($db->exists('SELECT v FROM t WHERE id = ?', 999));
        $this->assertTrue($db->exists('SELECT v FROM t WHERE id = ?', 2));
    }
}
