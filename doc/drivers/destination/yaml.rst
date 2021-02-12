YAML
====

Introduction
------------

The ``YamlDestinationDriver`` writes all results from a migration to a
series of YAML files.

One YAML file is created for each migrated entity. If your migration has
more than one id defined, subdirectories will be created, as in the
example below:

.. code-block:: php

   <?php

   /**
    * @DataMigration(
    *     name="Example Yaml Migration",
    *     source="sqlite:///%kernel.project_dir%/resources/source.sqlite",
    *     sourceIds={@IdField(name="id")},
    *     destination="/%kernel.project_dir%/resources/data/example",
    *     destinationIds={@IdField(name="group", type="string"), @IdField(name="identifier", type="string")}
    * )
    */

where the source data is:

.. list-table::
   :widths: auto
   :header-rows: 1
   :align: left

   * - group
     - identifier
     - value
   * - group1
     - entity1
     - 0
   * - group1
     - entity2
     - 0
   * - group2
     - entity3
     - 0
   * - group2
     - entity4
     - 0

will result in this file structure:

* resources/

  * data/

    * example/

      * group1/

        * entity1.yaml
        * entity2.yaml

      * group2/

        * entity3.yaml
        * entity4.yaml

The `Symfony Coding
Standards <https://symfony.com/doc/current/contributing/code/standards.html>`_
specify 4 spaces for each level of indentation. However, this tends to
muck up YAML files with nested lists and maps. As such, all generated
YAML files use 2 spaces for each level of indentation.

Usage
-----

If the destination directory does not exist, it will be created. All
subdirectories and YAML files will be created under the directory specified.
in the ``destination`` annotation field.

Some options and flags may be set to configure the YAML dumper:

Automatic reference generation
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

YAML supports aliases to refer to previously defined content. This is an
example from the `YAML Spec <http://yaml.org/spec/1.2/spec.html>`__:

.. code:: yaml

   First occurrence: &anchor Foo
   Second occurrence: *anchor
   Override anchor: &anchor Bar
   Reuse anchor: *anchor

The driver can search the output for repeated content and create these
anchors.

A few caveats:

*  This will significantly increase the time output takes, as the driver
   must create a list of every single value in the result to search for
   repetition.
*  Because this process must occur after the YAML representation has
   been created, it is not compatible with inline array representations.
*  As an automatic process, the driver has no way of determining if two
   repeated values are equal in context. Examine the output before
   editing manually to ensure that a referenced value is used where
   appropriate.

To enable this feature:

.. code:: php

   <?php

   /**
    * {@inheritdoc}
    * @param YamlDestinationDriver $destinationDriver
    */
   public function configureDestination(DestinationDriverInterface $destinationDriver)
   {
       // Automaticlly generate references for all keys
       $destinationDriver->setOption('refs', true);

       // Generate references only for keys matching these regular expressions
       // Depth is shown with a period (".") between each level
       // Matches "first.effect", "test.first.effect", but not "second.effect".
       $destinationDriver->setOption('refs', ['include' => [
           '`.*first\.effect`',
       ]]);

       // Generate references only for keys NOT matching these regular expressions
       // Matches "test.effect" but not "test.short_effect".
       $destinationDriver->setOption('refs', ['exclude' => [
           '`.*short_effect.*`',
       ]]);

       // Generate references with complex requirements
       // Matches "test.name", "test.effect", but not "test.short_effect", "other.short_effect"
       $destinationDriver->setOption('refs', [
           'include' => [
               '`test\..+`',
           ],
           'exclude' => [
               '`.+\.short_effect`',
           ]
       ]);
   }
