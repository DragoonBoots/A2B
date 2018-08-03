<?php

$versions = Sami\Version\GitVersionCollection::create(__DIR__)
    ->add('master');

$options = [
    'title' => 'A2B API',
    'remote_repository' => new Sami\RemoteRepository\GitLabRemoteRepository('DragoonBoots/a2b', __DIR__, 'https://gitlab.com/'),
    'versions' => $versions,
    'build_dir' => 'docs/api/build',
    'cache_dir' => 'docs/api/cache',
];

return new Sami\Sami('src', $options);
