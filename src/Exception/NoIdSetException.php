<?php


namespace DragoonBoots\A2B\Exception;

use Exception;
use Throwable;

/**
 * Thrown when a row does not a value set for a defined id.
 */
final class NoIdSetException extends Exception
{

    /**
     * NoIdSetException constructor.
     *
     * @param string $badId
     * @param array $presentValues
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $badId, array $presentValues, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            implode(
                ' ',
                [
                    'The row has no value set for the id field "%s".',
                    'Row values:',
                    '%s',
                ]
            ),
            $badId,
            var_export($presentValues, true)
        );
        parent::__construct($message, $code, $previous);
    }
}
