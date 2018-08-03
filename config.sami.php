<?php

$options = [
    'title' => 'A2B API',
    'remote_repository' => new Sami\RemoteRepository\GitLabRemoteRepository('DragoonBoots/a2b', __DIR__, 'https://gitlab.com/'),
    'build_dir' => 'docs/api/build',
    'cache_dir' => 'docs/api/cache',
];

$versions = Sami\Version\GitVersionCollection::create(__DIR__)
    ->add('master');

return new Sami\Sami('src', $options);
