<?php
declare(strict_types=1);

namespace ParagonIE\EasyDB\Tests;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyPlaceholder;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A SQLite connection that pretends to be a given server version.
 */
final class ServerVersionPdo extends PDO
{
    public int $versionReads = 0;

    public function __construct(private string $fakeVersion)
    {
        parent::__construct('sqlite::memory:');
    }

    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_SERVER_VERSION) {
            $this->versionReads++;
            return $this->fakeVersion;
        }
        return parent::getAttribute($attribute);
    }
}

/**
 * Exposes supportsReturning() and records every prepared statement.
 */
final class ReturningProbe extends EasyDB
{
    /** @var string[] */
    public array $statements = [];

    public function probe(): bool
    {
        return $this->supportsReturning();
    }

    public function prepare(mixed ...$args): PDOStatement
    {
        $this->statements[] = (string) $args[0];
        return parent::prepare(...$args);
    }
}

/**
 * insertGet() takes the value straight from INSERT ... RETURNING on MariaDB
 * 10.5 and later, instead of reading the row back.
 */
#[CoversClass(EasyDB::class)]
#[CoversClass(EasyPlaceholder::class)]
class InsertGetReturningTest extends TestCase
{
    public static function versionProvider(): array
    {
        return [
            'MariaDB 10.11, classic handshake prefix' => ['5.5.5-10.11.6-MariaDB-log', 'mysql', true],
            'MariaDB 11.4'                            => ['11.4.10-MariaDB-log', 'mysql', true],
            'MariaDB 10.5.0, first with RETURNING'    => ['10.5.0-MariaDB', 'mysql', true],
            'MariaDB 10.4'                            => ['10.4.32-MariaDB', 'mysql', false],
            'MariaDB 10.4, distro suffix'             => ['5.5.5-10.4.32-MariaDB-1:10.4.32+maria~ubu2004', 'mysql', false],
            'MySQL 8'                                 => ['8.0.36', 'mysql', false],
            'MySQL 8, distro suffix'                  => ['8.0.36-0ubuntu0.22.04.1', 'mysql', false],
            'MariaDB version, but pgsql engine'       => ['11.4.10-MariaDB-log', 'pgsql', false],
            'MariaDB version, but sqlite engine'      => ['11.4.10-MariaDB-log', 'sqlite', false],
        ];
    }

    #[DataProvider('versionProvider')]
    public function testSupportsReturning(string $version, string $engine, bool $expected): void
    {
        $db = new ReturningProbe(new ServerVersionPdo($version), $engine);
        $this->assertSame($expected, $db->probe());
    }

    public function testAnswerIsCachedPerConnection(): void
    {
        $pdo = new ServerVersionPdo('11.4.10-MariaDB-log');
        $db = new ReturningProbe($pdo, 'mysql');
        $db->probe();
        $db->probe();
        $this->assertSame(1, $pdo->versionReads);
    }

    private function mariaDb(string $version = '11.4.10-MariaDB-log'): ReturningProbe
    {
        // SQLite understands both backticks and RETURNING (3.35+), so the
        // MySQL flavoured query can really run here.
        $db = new ReturningProbe(new ServerVersionPdo($version), 'mysql');
        $db->run('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $db->statements = [];
        return $db;
    }

    public function testInsertGetUsesReturningAndNoReadBack(): void
    {
        $db = $this->mariaDb();
        $this->assertSame(1, $db->insertGet('t', ['v' => 'a'], 'id'));
        $this->assertSame(2, $db->insertGet('t', ['v' => 'b'], 'id'));

        $this->assertCount(2, $db->statements);
        foreach ($db->statements as $sql) {
            $this->assertStringStartsWith('INSERT INTO', $sql);
            $this->assertStringContainsString('RETURNING `id`', $sql);
        }
    }

    public function testInsertGetWithPlaceholderUsesReturning(): void
    {
        $db = $this->mariaDb();
        $id = $db->insertGet('t', ['v' => new EasyPlaceholder('UPPER(?)', 'b')], 'id');
        $this->assertSame(1, $id);
        $this->assertSame('B', $db->cell('SELECT v FROM t WHERE id = ?', $id));
    }

    public function testOlderServerStillReadsBack(): void
    {
        $db = $this->mariaDb('10.4.32-MariaDB');
        $this->assertSame(1, $db->insertGet('t', ['v' => 'a'], 'id'));
        $this->assertCount(2, $db->statements);
        $this->assertStringNotContainsString('RETURNING', $db->statements[0]);
        $this->assertStringStartsWith('SELECT', $db->statements[1]);
    }
}
