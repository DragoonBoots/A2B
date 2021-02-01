Drivers
=======

Source
------
.. toctree::
   :hidden:
   :maxdepth: 2
   :glob:

   drivers/source/*

.. list-table::
   :widths: auto
   :header-rows: 1
   :width: 100%

   - * Driver
     * Description
   - * :doc:`drivers/source/dbal`
     * Anything supported by the `Doctrine DBAL <https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/index.html>`_
   - * :doc:`drivers/source/csv`
     * A CSV file
   - * :doc:`drivers/source/yaml`
     * A directory filled with YAML files

Destination
-----------

.. toctree::
   :hidden:
   :maxdepth: 2
   :glob:

   drivers/destination/*

.. list-table::
   :widths: auto
   :header-rows: 1
   :width: 100%

   - * Driver
     * Description
   - * :doc:`drivers/destination/csv`
     * One CSV file per migration
   - * :doc:`drivers/destination/yaml`
     * A directory (with optional subdirectories depending on migration ids) with one YAML file per entity.
   - * :doc:`drivers/destination/doctrine`
     * `Doctrine ORM <https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html>`_ entities
