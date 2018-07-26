<?php


namespace DragoonBoots\A2B\Exception;

use Throwable;

/**
 * Thrown when no source was set on the source driver.
 */
final class NoSourceException extends \Exception
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
            $message = 'No source was set for the driver.  Call setSource() with the migration source uri.';
        }
        parent::__construct($message, $code, $previous);
    }
}
