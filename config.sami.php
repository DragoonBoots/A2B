<?php

class PublicNotInternalFilter extends Sami\Parser\Filter\PublicFilter
{

    public function acceptMethod(Sami\Reflection\MethodReflection $method)
    {
        return parent::acceptMethod($method)
            && !strpos($method->getDocComment(), '@internal');
    }
}

$options = [
    'title' => 'A2B API',
    'remote_repository' => new Sami\RemoteRepository\GitLabRemoteRepository('DragoonBoots/a2b', __DIR__, 'https://gitlab.com/'),
    'build_dir' => 'docs/api/build',
    'cache_dir' => 'docs/api/cache',
    'filter' => new PublicNotInternalFilter(),
];

return new Sami\Sami('src', $options);
