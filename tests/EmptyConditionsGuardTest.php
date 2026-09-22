<?php
declare(strict_types=1);

namespace ParagonIE\EasyDB\Tests;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyStatement;
use ParagonIE\EasyDB\Exception\DeleteConditionMustBeNonEmpty;
use ParagonIE\EasyDB\Exception\UpdateSetAndConditionMustBeNonEmpty;
use ParagonIE\EasyDB\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The guards against empty conditions must stop the query before it runs:
 * an empty EasyStatement renders as "1 = 1", so without them delete() and
 * update() would silently hit every row of the table.
 */
#[CoversClass(EasyDB::class)]
#[CoversClass(EasyStatement::class)]
#[CoversClass(Factory::class)]
#[CoversClass(DeleteConditionMustBeNonEmpty::class)]
#[CoversClass(UpdateSetAndConditionMustBeNonEmpty::class)]
class EmptyConditionsGuardTest extends EasyDBWriteTestCase
{
    private function seed(EasyDB $db): void
    {
        $db->insertMany('irrelevant_but_valid_tablename', [
            ['foo' => 'a'],
            ['foo' => 'b'],
            ['foo' => 'c'],
        ]);
    }

    private function rows(EasyDB $db): array
    {
        return $db->column('SELECT foo FROM irrelevant_but_valid_tablename ORDER BY foo');
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     * @param callable $cb
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testDeleteWithEmptyEasyStatementLeavesTableIntact(callable $cb): void
    {
        $db = $this->easyDBExpectedFromCallable($cb);
        $this->seed($db);
        try {
            $db->delete('irrelevant_but_valid_tablename', EasyStatement::open());
            $this->fail('delete() with an empty EasyStatement must throw');
        } catch (DeleteConditionMustBeNonEmpty $e) {
        }
        $this->assertSame(['a', 'b', 'c'], $this->rows($db));
    }

    /**
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     * @param callable $cb
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testUpdateWithEmptyEasyStatementLeavesTableIntact(callable $cb): void
    {
        $db = $this->easyDBExpectedFromCallable($cb);
        $this->seed($db);
        try {
            $db->update('irrelevant_but_valid_tablename', ['foo' => 'z'], EasyStatement::open());
            $this->fail('update() with an empty EasyStatement must throw');
        } catch (UpdateSetAndConditionMustBeNonEmpty $e) {
        }
        $this->assertSame(['a', 'b', 'c'], $this->rows($db));
    }

    /**
     * The guard must not get in the way of a legitimate statement.
     *
     * @dataProvider goodFactoryCreateArgument2EasyDBProvider
     * @param callable $cb
     */
    #[DataProvider("goodFactoryCreateArgument2EasyDBProvider")]
    public function testDeleteWithNonEmptyEasyStatementStillWorks(callable $cb): void
    {
        $db = $this->easyDBExpectedFromCallable($cb);
        $this->seed($db);
        $n = $db->delete('irrelevant_but_valid_tablename', EasyStatement::open()->with('foo = ?', 'b'));
        $this->assertSame(1, $n);
        $this->assertSame(['a', 'c'], $this->rows($db));
    }
}
