<?php


namespace DragoonBoots\A2B\Factory;


use Symfony\Component\Finder\Finder;

/**
 * Factory class for Finder.
 */
class FinderFactory
{

    /**
     * @return Finder
     */
    public function get(): Finder
    {
        return new Finder();
    }
}
