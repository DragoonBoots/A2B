<?php

namespace DragoonBoots\A2B\Drivers;

use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Exception\NoDriverForSchemeException;
use DragoonBoots\A2B\Exception\NonexistentDriverException;
use DragoonBoots\A2B\Exception\UnclearDriverException;


/**
 * Manage drivers
 */
interface DriverManagerInterface
{

    /**
     * @return Collection|SourceDriverInterface[]
     */
    public function getSourceDrivers(): Collection;

    /**
     * @param string $driverName
     *
     * @return SourceDriverInterface
     *
     * @throws NonexistentDriverException
     */
    public function getSourceDriver(string $driverName): SourceDriverInterface;

    /**
     * @param string $scheme
     *
     * @return SourceDriverInterface
     *
     * @throws NoDriverForSchemeException
     *   Thrown when no driver implements the given scheme.
     * @throws UnclearDriverException
     *   Thrown when more than one driver implements the given scheme.
     */
    public function getSourceDriverForScheme(string $scheme): SourceDriverInterface;

    /**
     * @return Collection|DestinationDriverInterface[]
     */
    public function getDestinationDrivers(): Collection;

    /**
     * @param string $driverName
     *
     * @return DestinationDriverInterface
     *
     * @throws NonexistentDriverException
     */
    public function getDestinationDriver(string $driverName): DestinationDriverInterface;

    /**
     * @param string $scheme
     *
     * @return DestinationDriverInterface
     *
     * @throws NoDriverForSchemeException
     *   Thrown when no driver implements the given scheme.
     * @throws UnclearDriverException
     *   Thrown when more than one driver implements the given scheme.
     */
    public function getDestinationDriverForScheme(string $scheme): DestinationDriverInterface;
}
