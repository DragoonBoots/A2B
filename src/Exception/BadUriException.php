<?php


namespace DragoonBoots\A2B\Exception;

use Throwable;

/**
 * Thrown when a source/destination URI is not valid.
 */
final class BadUriException extends \Exception
{

    /**
     * BadUriException constructor.
     *
     * @param string         $uri
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $uri = '', int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The URI "%s" is not valid.', $uri);
        parent::__construct($message, $code, $previous);
    }
}
