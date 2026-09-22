<?php
declare(strict_types=1);
namespace ParagonIE\EasyDB\Exception;

use ParagonIE\Corner\CornerTrait;
use Throwable;

/**
 * Class DeleteConditionMustBeNonEmpty
 * @package ParagonIE\EasyDB\Exception
 * @api
 */
class DeleteConditionMustBeNonEmpty extends EasyDBException
{
    use CornerTrait;

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->supportLink = 'https://github.com/wergio/easydb';
        $this->helpfulMessage = "The conditions passed to delete() must not be empty.

An empty conditions array, or an EasyStatement with no conditions (which renders
as \"1 = 1\"), would delete every row in the table.

If that is really what you want, say so explicitly:

    \$db->run('DELETE FROM table_name');";
    }
}
