Introduction
------------
The `YamlDestinationDriver` writes all results from a migration to a series of YAML files.

One YAML file is created for each migrated entity.  If your migration has more than one
id defined, subdirectories will be created, as in the example below:

```php
/**
 * @DataMigration(
 *     name="Example Yaml Migration",
 *     source="sqlite:///%kernel.project_dir%/resources/source.sqlite",
 *     sourceIds={@IdField(name="id")},
 *     destination="yaml:///%kernel.project_dir%/resources/data/example",
 *     destinationIds={@IdField(name="group", type="string"), @IdField(name="identifier", type="string")}
 * )
 */
```
with the entities
```php
[
    'group' => 'group1',
    'identifier' => 'entity1',
];

[
    'group' => 'group1',
    'identifier' => 'entity2',
];

[
    'group' => 'group2',
    'identifier' => 'entity3',
];

[
    'group' => 'group2',
    'identifier' => 'entity4',
];
$entity
```
will result in this file structure
<pre>
resources/
  - data/
    - example/
      - group1/
        - entity1.yaml
        - entity2.yaml
      - group2/
        - entity3.yaml
        - entity4.yaml
</pre>

The [Symfony Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html)
specify 4 spaces for each level of indentation.  However, this tends to muck up
YAML files with nested lists and maps.  As such, all generated YAML files use
2 spaces for each level of indentation.

Supported URLs
--------------
URLs should be in the format `yaml://OUTPUT_PATH`.  If the destination
directory does not exist, it will be created.  All subdirectories and YAML
files will be created under OUTPUT_PATH.

Usage
-----
Some options and flags may be set to configure the YAML dumper.

### Array inlining
See [here](https://symfony.com/doc/current/components/yaml.html#array-expansion-and-inlining)
for more examples on what this affects.
```php
// Start inlining arrays 5 levels deep
$destinationDriver->setOption('inline', 5);
```

**NOTE:** Array inlining is not compatible with automatic reference generation.

### Automatic reference generation
YAML supports aliases to refer to previously defined content.  This is an
example from the [YAML Spec](http://yaml.org/spec/1.2/spec.html):
```yaml
First occurrence: &anchor Foo
Second occurrence: *anchor
Override anchor: &anchor Bar
Reuse anchor: *anchor
```

The driver can search the output for repeated content and create these anchors.

A few caveats:
- This will significantly increase the time output takes, as the driver must
  create a list of every single value in the result to search for repetition.
- Because this process must occur after the YAML representation has been
  created, it is not compatible with inline array representations.
- As an automatic process, the driver has no way of determining if two repeated
  values are equal in context.  Examine the output before editing manually
  to ensure that a referenced value is used where appropriate.
  
To enable this feature:
```php
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

```

### Advanced flags
The Symfony YAML dumper supports a number of flags for advanced use cases.
See [here](https://symfony.com/doc/current/components/yaml.html#advanced-usage-flags)
for a list of flags with examples.
```php
// Dump objects as YAML maps
$destinationDriver->setFlag(Yaml::DUMP_OBJECT_AS_MAP);
```

By default, strings with multiple lines (i.e. with one or more `\n` characters)
are dumped as multi-line literals:
```yaml
string: |
    Multiple
    Line
    String
```

To change this behavior, use `unsetFlag()`.
```php
// Inline multi-line string literals
$destinationDriver->unsetFlag(Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
```
