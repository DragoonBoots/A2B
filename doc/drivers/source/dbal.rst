DBAL
====

Introduction
------------

The ``DbalSourceDriver`` supports using anything supported by the
`Doctrine DBAL <https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/index.html>`_
as a source.

Usage
-----

Use a valid Doctrine DBAL source URL for ``source``. See `Doctrine's
website <https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url>`__
for a list of supported URL formats.

In the migration's ``configureSource``, two steps are required. For the
main source data set, call ``setStatement`` with the query. For fetching
the count, call ``setCountStatement`` with a query; the first field
returned is used as the count.

.. code-block:: php

   <?php

   public function configureSource(SourceDriverInterface $sourceDriver)
   {
       $sourceDriver->setStatement(
           <<<SQL
   SELECT
       "generations"."id",
       "generation_names"."name",
       "regions"."identifier" AS "main_region"
   FROM "generations"
       JOIN "generation_names" ON "generations"."id" = "generation_names"."generation_id"
       JOIN "regions" ON "generations"."main_region_id" = "regions"."id";
   SQL
       );

       $sourceDriver->setCountStatement(
           <<<SQL
   SELECT
       count(*)
   FROM "generations";
   SQL
       );
   }

If you need to do something complicated, pass a ``Statement`` object to
either method instead of an SQL string.
