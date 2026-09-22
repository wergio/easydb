<?php
declare(strict_types=1);
namespace ParagonIE\EasyDB\Exception;

use ParagonIE\Corner\CornerTrait;
use Throwable;

/**
 * Class UpdateSetAndConditionMustBeNonEmpty
 * @package ParagonIE\EasyDB\Exception
 * @api
 */
class UpdateSetAndConditionMustBeNonEmpty extends EasyDBException
{
    use CornerTrait;

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->supportLink = 'https://github.com/wergio/easydb';
        $this->helpfulMessage = "Both the changes and the conditions passed to update() must not be empty.

Empty changes would be a no-op that hides a bug in the caller. Empty conditions,
or an EasyStatement with no conditions (which renders as \"1 = 1\"), would update
every row in the table.

If updating every row is really what you want, say so explicitly:

    \$db->run('UPDATE table_name SET column = ?', \$value);";
    }
}
