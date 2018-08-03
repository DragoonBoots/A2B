A2B
===

[![pipeline status](https://gitlab.com/DragoonBoots/a2b/badges/master/pipeline.svg)](https://gitlab.com/DragoonBoots/a2b/commits/master)
[![coverage report](https://gitlab.com/DragoonBoots/a2b/badges/master/coverage.svg)](https://gitlab.com/DragoonBoots/a2b/commits/master)

A2B is a data miration tool for Symfony.  Features include:
- Built-in and custom sources and destinations
- Tracks previously migrated data, allowing old data to remain in use while new data is prepared
- Supports complex data sources where one row may reference another

Sources
-------
- Anything supported by the [Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/2.7/reference/introduction.html#introduction)
- CSV
- YAML
- Custom sources (documentation forthcoming)

Destinations
------------
- Doctrine ORM entities
- CSV
- YAML
- Custom destinations (documentation forthcoming)

Installation
------------
Add the following to your composer.json

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://dragoonboots.gitlab.io/packagist/"
    }
  ]
}
```

Then run `composer require dragoonboots/a2b:dev-master`.