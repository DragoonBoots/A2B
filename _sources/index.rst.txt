A2B
===

.. toctree::
   :maxdepth: 2
   :caption: Contents:

   usage
   drivers
   upgrading

Introduction
------------

A2B is a data miration tool for Symfony.  Features include:
* Built-in and custom sources and destinations
* Tracks previously migrated data, allowing old data to remain in use while new data is prepared
* Supports complex data sources where one row may reference another

Sources
-------
* Anything supported by the `Doctrine DBAL <https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/index.html>`_
* CSV
* YAML
* Custom sources (documentation forthcoming)

Destinations
------------
* Doctrine ORM entities
* CSV
* YAML
* Custom destinations (documentation forthcoming)

Installation
------------
Add the following to your composer.json

.. code-block:: json

   {
     "repositories": [
       {
         "type": "composer",
         "url": "https://gitlab.com/api/v4/group/dragoonboots-packages/-/packages/composer/packages.json"
       }
     ]
   }

Then run ``composer require dragoonboots/a2b:dev-master``.
