<?php
declare(strict_types=1);
namespace ParagonIE\EasyDB\Tests\Driver;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyPlaceholder;
use ParagonIE\EasyDB\Exception\ConstructorFailed;
use ParagonIE\EasyDB\Factory;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * insertGet() on a real MariaDB 10.5+, on columns that transform their input:
 * the old read-back (matching the values just inserted) cannot find the row
 * again, INSERT ... RETURNING can. Skipped on MySQL and older MariaDB.
 */
#[CoversClass(EasyDB::class)]
#[CoversClass(Factory::class)]
#[CoversClass(EasyPlaceholder::class)]
class MariaDbReturningTest extends TestCase
{
    private ?EasyDB $db = null;

    protected function setUp(): void
    {
        $host = \getenv('MYSQL_HOST') ?: '127.0.0.1';
        $name = \getenv('MYSQL_DB') ?: 'easydb';
        try {
            $this->db = Factory::create(
                "mysql:host={$host};dbname={$name}",
                \getenv('MYSQL_USER') ?: 'root',
                \getenv('MYSQL_PASS') ?: '',
                [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]
            );
        } catch (PDOException|ConstructorFailed $e) {
            $this->markTestSkipped($e->getMessage());
        }
        $version = (string) $this->db->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        $version = (string) \preg_replace('/^5\.5\.5-/', '', $version);
        if (\stripos($version, 'mariadb') === false
            || \preg_match('/^(\d+(?:\.\d+)*)/', $version, $m) !== 1
            || \version_compare($m[1], '10.5', '<')
        ) {
            $this->markTestSkipped("needs MariaDB 10.5 or later, got {$version}");
        }
        $this->db->run('DROP TABLE IF EXISTS returning_test');
        $this->db->run(
            'CREATE TABLE returning_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                f FLOAT,
                d DECIMAL(5,2),
                s VARCHAR(20)
            )'
        );
    }

    protected function tearDown(): void
    {
        $this->db?->run('DROP TABLE IF EXISTS returning_test');
    }

    public function testFloatColumn(): void
    {
        // 0.1 is stored as single precision: WHERE f = 0.1 no longer matches
        $id = $this->db->insertGet('returning_test', ['f' => 0.1], 'id');
        $this->assertSame(1, (int) $id);
    }

    public function testDecimalColumnWithSmallerScale(): void
    {
        // 1.005 is stored as 1.01: WHERE d = 1.005 no longer matches
        $id = $this->db->insertGet('returning_test', ['d' => 1.005], 'id');
        $this->assertSame(1, (int) $id);
        $this->assertSame('1.01', (string) $this->db->cell('SELECT d FROM returning_test WHERE id = ?', $id));
    }

    public function testReturnsAnyColumnNotJustTheKey(): void
    {
        $this->db->insertGet('returning_test', ['s' => 'first'], 'id');
        $this->assertSame('SECOND', $this->db->insertGet(
            'returning_test',
            ['s' => new EasyPlaceholder('UPPER(?)', 'second')],
            's'
        ));
    }

    public function testSequentialIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = (int) $this->db->insertGet('returning_test', ['f' => $i / 3], 'id');
        }
        $this->assertSame([1, 2, 3, 4, 5], $ids);
    }
}
