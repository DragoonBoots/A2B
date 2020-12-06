<?php


namespace DragoonBoots\A2B\Exception;

use Exception;
use Throwable;

/**
 * Thrown when a requested driver does not exist.
 */
final class NonexistentDriverException extends Exception
{

    /**
     * NonexistentDriverException constructor.
     *
     * @param string         $driverName
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $driverName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The driver "%s" does not exist.', $driverName);
        parent::__construct($message, $code, $previous);
    }
}
