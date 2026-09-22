<?php
declare(strict_types=1);

namespace ParagonIE\EasyDB\Tests;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\Exception\InvalidIdentifier;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dashes and spaces in identifiers, on every engine except SQLite.
 *
 * escapeIdentifier() only depends on the engine name, so an in-memory SQLite
 * connection declared as another engine is enough to exercise its rules.
 */
#[CoversClass(EasyDB::class)]
class EscapeIdentifierExtendedCharsTest extends TestCase
{
    private function db(string $engine, bool $allowSeparators = false): EasyDB
    {
        $db = new EasyDB(new PDO('sqlite::memory:'), $engine);
        $db->setAllowSeparators($allowSeparators);
        return $db;
    }

    public static function engineProvider(): array
    {
        return [
            'mysql' => ['mysql', '`', '`'],
            'pgsql' => ['pgsql', '"', '"'],
        ];
    }

    #[DataProvider('engineProvider')]
    public function testDashIsAllowed(string $engine, string $open, string $close): void
    {
        $this->assertSame(
            "{$open}dbunico-despar26{$close}",
            $this->db($engine)->escapeIdentifier('dbunico-despar26')
        );
    }

    #[DataProvider('engineProvider')]
    public function testSpaceIsAllowed(string $engine, string $open, string $close): void
    {
        $this->assertSame(
            "{$open}colonna con spazi{$close}",
            $this->db($engine)->escapeIdentifier('colonna con spazi')
        );
    }

    #[DataProvider('engineProvider')]
    public function testSurroundingWhitespaceIsDropped(string $engine, string $open, string $close): void
    {
        foreach ([false, true] as $sep) {
            $this->assertSame("{$open}foo{$close}", $this->db($engine, $sep)->escapeIdentifier('  foo '));
        }
    }

    public function testWhitespaceOnlyIsRejected(): void
    {
        $this->expectException(InvalidIdentifier::class);
        $this->db('mysql')->escapeIdentifier('   ');
    }

    public function testQualifiedNameWithDashes(): void
    {
        $this->assertSame(
            '`dbunico-despar26`.`utentiweb`',
            $this->db('mysql', true)->escapeIdentifier('dbunico-despar26.utentiweb')
        );
    }

    /**
     * The real flow in the application: the database name is escaped first,
     * then the result is concatenated with the table name and escaped again.
     * With separators allowed the backticks of the first pass are dropped,
     * which is what makes this work.
     */
    public function testAlreadyEscapedPrefixIsReEscaped(): void
    {
        $db = $this->db('mysql', true);
        $preamble = $db->escapeIdentifier('dbunico-despar26') . '.';
        $this->assertSame('`dbunico-despar26`.`utentiweb`', $db->escapeIdentifier($preamble . 'utentiweb'));
    }

    public static function stillInvalidProvider(): array
    {
        return [
            'semicolon' => ['col; DROP TABLE x'],
            'quote'     => ["col'x"],
            'backtick'  => ['col`x'],
            'paren'     => ['col(x)'],
        ];
    }

    /**
     * Allowing dashes and spaces must not open the door to anything else.
     */
    #[DataProvider('stillInvalidProvider')]
    public function testOtherCharactersAreStillRejected(string $identifier): void
    {
        $this->expectException(InvalidIdentifier::class);
        $this->db('mysql')->escapeIdentifier($identifier);
    }

    /**
     * SQLite keeps upstream's stricter rules, as on the 2.x line.
     */
    public function testSqliteStillRejectsDashAndSpace(): void
    {
        foreach (['foo-4', 'foo 3'] as $identifier) {
            try {
                $this->db('sqlite')->escapeIdentifier($identifier);
                $this->fail("sqlite must reject '{$identifier}'");
            } catch (InvalidIdentifier $e) {
                $this->assertInstanceOf(InvalidIdentifier::class, $e);
            }
        }
    }

    /**
     * Known limitation, pinned on purpose: with separators allowed every dot
     * is a separator, so a column literally named "D.3 R1" is split. If this
     * ever changes, it must be a deliberate decision.
     */
    public function testDottedNameIsSplitWhenSeparatorsAllowed(): void
    {
        $this->assertSame('`D`.`3 R1`', $this->db('mysql', true)->escapeIdentifier('D.3 R1'));
    }
}
