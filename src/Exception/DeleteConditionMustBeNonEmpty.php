<?php
declare(strict_types=1);
namespace ParagonIE\EasyDB\Exception;

use ParagonIE\Corner\CornerInterface;
use ParagonIE\Corner\CornerTrait;

/**
 * Class DeleteConditionMustBeNonEmpty
 * @package ParagonIE\EasyDB\Exception
 */
class DeleteConditionMustBeNonEmpty extends \Exception implements CornerInterface
{
    use CornerTrait;

    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->helpfulMessage = "By default, the conditions array passed to delete must be non-empty to avoid foot-bullets :-)";
    }
}
