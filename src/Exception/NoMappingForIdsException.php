<?php


namespace DragoonBoots\A2B\Exception;


use Exception;
use Throwable;

/**
 * Thrown when no mapping was found for the given ids.
 */
final class NoMappingForIdsException extends Exception
{

    public function __construct(array $ids, string $entity = 'entity', int $code = 0, Throwable $previous = null)
    {
        $message = sprintf("No %s mapping found for ids:\n%s\n", $entity, var_export($ids, true));
        parent::__construct($message, $code, $previous);
    }
}
