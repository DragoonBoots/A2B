<?php

namespace DragoonBoots\A2B\Drivers;

use Doctrine\Common\Collections\Collection;
use DragoonBoots\A2B\Exception\NonexistentDriverException;


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
}
