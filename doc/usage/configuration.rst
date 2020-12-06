Configuration
=============

A2B is designed to work with most ETL needs out of the box. If you find
something that may require additional configuration, `file an
issue <https://gitlab.com/dragoonboots-packages/a2b/-/issues>`_.

.. _config-sources-destinations:

Sources and Destinations
------------------------

Oftentimes data must be extracted from a single source (e.g. an old
database) for many different migrations. To avoid repetition, a special
key may be used.

In ``config/packages/a2b.yaml``, define static sources and destinations:

.. code-block:: yaml

   a2b:
     sources:
       - name: old_db
         uri: 'sqlite:///srv/data/db.sqlite'
         driver: 'DragoonBoots\A2B\Drivers\Source\DbalSourceDriver'
     destinations:
       - name: entity_user
         uri: 'App\Entity\User'
         driver: 'DragoonBoots\A2B\Drivers\Destination\DoctrineDestinationDriver'

These can then be used in place of a URI in the migration definition:

.. code-block:: php

   <?php

   /**
    * Example migration
    *
    * @DataMigration(
    *     name="Example",
    *     group="Test",
    *     source="old_db",
    *     destination="entity_user",
    *     sourceIds={@IdField(name="id")},
    *     destinationIds={@IdField(name="id")}
    * )
    */
