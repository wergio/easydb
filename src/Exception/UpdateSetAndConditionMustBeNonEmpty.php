<?php
declare(strict_types=1);
namespace ParagonIE\EasyDB\Exception;

use ParagonIE\Corner\CornerInterface;
use ParagonIE\Corner\CornerTrait;

/**
 * Class UpdateSetAndConditionMustBeNonEmpty
 * @package ParagonIE\EasyDB\Exception
 */
class UpdateSetAndConditionMustBeNonEmpty extends \Exception implements CornerInterface
{
    use CornerTrait;

    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->helpfulMessage = "By default, the changes and conditions arrays passed to update must be non-empty to avoid foot-bullets :-)";
    }
}
