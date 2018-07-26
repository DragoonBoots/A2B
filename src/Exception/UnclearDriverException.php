<?php


namespace DragoonBoots\A2B\Exception;

use Throwable;

/**
 * Thrown when more than one driver implements a given scheme.
 */
final class UnclearDriverException extends \Exception
{

    /**
     * UnclearDriverException constructor.
     *
     * @param string         $scheme
     * @param array          $driverNames
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $scheme, array $driverNames, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
          implode(
            "\n", [
              'More than one driver implements the scheme "%s": %s',
              'Specify the driver using the sourceDriver or destinationDriver property in the @DataMigration annotation.',
            ]
          ), $scheme, implode(', ', $driverNames)
        );
        parent::__construct($message, $code, $previous);
    }
}
