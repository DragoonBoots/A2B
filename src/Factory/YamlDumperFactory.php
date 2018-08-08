<?php


namespace DragoonBoots\A2B\Factory;


use Symfony\Component\Yaml\Dumper;

class YamlDumperFactory
{

    /**
     * Get a dumper with the given indentation.
     *
     * @param int $indentation
     *
     * @return Dumper
     */
    public function get(int $indentation = 2)
    {
        static $dumpers = [];
        if (!isset($dumpers[$indentation])) {
            $dumpers[$indentation] = new Dumper($indentation);
        }

        return $dumpers[$indentation];
    }
}
