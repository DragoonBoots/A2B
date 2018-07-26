<?php


namespace DragoonBoots\A2B;


use DragoonBoots\A2B\DependencyInjection\A2BExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class A2BBundle extends Bundle
{

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new A2BExtension();
    }

}
