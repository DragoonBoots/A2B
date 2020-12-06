<?php


namespace DragoonBoots\A2B\Exception;

use Exception;
use Throwable;

/**
 * Thrown when a requested driver does not exist for the given scheme.
 */
final class NoDriverForSchemeException extends Exception
{

    /**
     * NoDriverForSchemeException constructor.
     *
     * @param string         $scheme
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $scheme, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('No driver was found for the scheme "%s".', $scheme);
        parent::__construct($message, $code, $previous);
    }
}
