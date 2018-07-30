<?php


namespace DragoonBoots\A2B\Exception;

use Throwable;

/**
 * Thrown when no destination was set on the source driver.
 */
final class NoDestinationException extends \Exception
{

    /**
     * NoSourceException constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'No destination was set for the driver.  Call configure() with the migration definition.';
        }
        parent::__construct($message, $code, $previous);
    }
}
