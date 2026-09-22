<?php
declare(strict_types=1);

namespace ParagonIE\EasyDB\Tests;

use ParagonIE\EasyDB\EasyDB;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Records every attribute set on the connection, so the constructor can be
 * checked without a real SQL Server.
 */
final class AttributeRecordingPdo extends PDO
{
    /** @var array<int, mixed> */
    public array $set = [];

    public function setAttribute(int $attribute, mixed $value): bool
    {
        $this->set[$attribute] = $value;
        return parent::setAttribute($attribute, $value);
    }
}

/**
 * The sqlsrv driver (Microsoft's pdo_sqlsrv) gets the same treatment as the
 * legacy "mssql" name. No SQL Server needed: everything here only depends on
 * the engine name.
 */
#[CoversClass(EasyDB::class)]
class SqlSrvWithoutServerTest extends TestCase
{
    public function testConstructorDoesNotSetEmulatePreparesOnSqlsrv(): void
    {
        $pdo = new AttributeRecordingPdo('sqlite::memory:');
        new EasyDB($pdo, 'sqlsrv');
        $this->assertArrayNotHasKey(PDO::ATTR_EMULATE_PREPARES, $pdo->set);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->set[PDO::ATTR_ERRMODE]);
    }

    public function testConstructorStillDisablesEmulatePreparesElsewhere(): void
    {
        foreach (['mysql', 'pgsql', 'sqlite', 'mssql'] as $engine) {
            $pdo = new AttributeRecordingPdo('sqlite::memory:');
            new EasyDB($pdo, $engine);
            $this->assertArrayHasKey(PDO::ATTR_EMULATE_PREPARES, $pdo->set, $engine);
            $this->assertFalse($pdo->set[PDO::ATTR_EMULATE_PREPARES], $engine);
        }
    }

    public function testDriverIsStillDetectedWhenNotGiven(): void
    {
        $pdo = new AttributeRecordingPdo('sqlite::memory:');
        $db = new EasyDB($pdo);
        $this->assertSame('sqlite', $db->getDriver());
        $this->assertFalse($pdo->set[PDO::ATTR_EMULATE_PREPARES]);
    }

    public function testIdentifiersUseSquareBrackets(): void
    {
        $db = new EasyDB(new PDO('sqlite::memory:'), 'sqlsrv');
        $this->assertSame('[users]', $db->escapeIdentifier('users'));
        $this->assertSame('[dbunico-x]', $db->escapeIdentifier('dbunico-x'));

        $db->setAllowSeparators(true);
        $this->assertSame('[dbunico-x].[users]', $db->escapeIdentifier('dbunico-x.users'));
    }

    public function testLikeValueEscapesCharacterRanges(): void
    {
        $db = new EasyDB(new PDO('sqlite::memory:'), 'sqlsrv');
        $this->assertSame('\[a-z\]\%\_', $db->escapeLikeValue('[a-z]%_'));
    }

    public function testLegacyMssqlNameIsUnchanged(): void
    {
        $db = new EasyDB(new PDO('sqlite::memory:'), 'mssql');
        $this->assertSame('[users]', $db->escapeIdentifier('users'));
        $this->assertSame('\[a-z\]', $db->escapeLikeValue('[a-z]'));
    }
}
