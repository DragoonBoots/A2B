<?php


namespace DragoonBoots\A2B\Exception;

use Exception;
use Throwable;

/**
 * Thrown when a requested migration does not exist.
 */
final class NonexistentMigrationException extends Exception
{

    /**
     * NonexistentMigrationException constructor.
     *
     * @param string         $scheme
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $scheme, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The migration "%s" does not exist.', $scheme);
        parent::__construct($message, $code, $previous);
    }
}
