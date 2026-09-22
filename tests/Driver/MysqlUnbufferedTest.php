<?php
declare(strict_types=1);
namespace ParagonIE\EasyDB\Tests\Driver;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The whole MySQL driver suite again, with unbuffered queries: the setting
 * the applications built on this fork run with. An unbuffered result set that
 * is left open blocks the connection (SQLSTATE HY000, error 2014).
 */
#[CoversClass(EasyDB::class)]
#[CoversClass(Factory::class)]
class MysqlUnbufferedTest extends MysqlTest
{
    protected function getOptions(): array
    {
        return [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false];
    }

    private function seedUsers(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->db->insert('users', ['username' => "u{$i}", 'email' => "u{$i}@example.com"]);
        }
    }

    public function testConnectionIsReallyUnbuffered(): void
    {
        $this->assertFalse((bool) $this->db->getPdo()->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY));
    }

    public function testSafeQueryGeneratorStoppedEarlyDoesNotBlockTheConnection(): void
    {
        $this->seedUsers(50);
        foreach ($this->db->safeQueryGenerator('SELECT * FROM users ORDER BY userid') as $row) {
            break;
        }
        $this->assertSame(50, (int) $this->db->single('SELECT COUNT(*) FROM users'));
    }

    public function testColumnGeneratorStoppedEarlyDoesNotBlockTheConnection(): void
    {
        $this->seedUsers(50);
        foreach ($this->db->columnGenerator('SELECT username FROM users ORDER BY userid') as $name) {
            break;
        }
        $this->assertSame(50, (int) $this->db->single('SELECT COUNT(*) FROM users'));
    }

    public function testGeneratorFullyConsumedThenAnotherQuery(): void
    {
        $this->seedUsers(50);
        $this->assertCount(50, iterator_to_array($this->db->safeQueryGenerator('SELECT * FROM users'), false));
        $this->assertSame(50, (int) $this->db->single('SELECT COUNT(*) FROM users'));
    }

    /**
     * The usual application pattern: the generator is kept in a variable,
     * which outlives the loop.
     */
    public function testFullyConsumedGeneratorKeptInAVariableDoesNotBlock(): void
    {
        $this->seedUsers(50);
        $rows = $this->db->safeQueryGenerator('SELECT * FROM users');
        $n = 0;
        foreach ($rows as $row) {
            $n++;
        }
        $this->assertSame(50, $n);
        $this->assertSame(50, (int) $this->db->single('SELECT COUNT(*) FROM users'));
    }

    /**
     * Known limitation, pinned on purpose: a generator stopped early and still
     * referenced keeps its unbuffered result set open, so the connection is
     * blocked until the generator is released. Nothing inside the generator
     * can prevent this; the caller has to unset() it.
     */
    public function testGeneratorStoppedEarlyAndKeptAliveBlocksUntilReleased(): void
    {
        $this->seedUsers(50);
        $rows = $this->db->safeQueryGenerator('SELECT * FROM users ORDER BY userid');
        foreach ($rows as $row) {
            break;
        }
        try {
            $this->db->single('SELECT COUNT(*) FROM users');
            $this->fail('the connection was expected to be blocked (error 2014)');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('2014', $e->getMessage());
        }
        unset($rows);
        $this->assertSame(50, (int) $this->db->single('SELECT COUNT(*) FROM users'));
    }

    /**
     * The path that failed with the 2.x exec() patch: a SELECT through
     * safeQuery() without parameters, asking for the affected rows.
     */
    public function testSelectWithoutParamsReturningAffectedRows(): void
    {
        $this->seedUsers(3);
        $this->db->safeQuery('SELECT * FROM users', [], PDO::FETCH_ASSOC, true);
        $this->assertSame(3, (int) $this->db->single('SELECT COUNT(*) FROM users'));
    }
}
