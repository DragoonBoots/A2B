All data migrations have some basic things in common:
- They must implement `DragoonBoots\A2B\DataMigration\DataMigrationInterface`
- They will probably want to extend `DragoonBoots\A2B\DataMigration\AbstractDataMigration`
- They must be annotated with `DragoonBoots\A2B\Annotations\DataMigration`

Definition
----------
All data migrations have their configuration defined in their `@DataMigration`
annotation.  Here's an example:

```php
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\AbstractDataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;

/**
 * Example migration
 *
 * @DataMigration(
 *     name="Example",
 *     group="Test",
 *     source="sqlite:///%kernel.project_dir%/resources/sourcedb.sqlite",
 *     sourceDriver="DragoonBoots\A2B\Drivers\Source\DbalSourceDriver",
 *     destination="csv:///%kernel.project_dir%/resources/data/data.csv",
 *     destinationDriver="DragoonBoots\A2B\Drivers\Destination\CsvDestinationDriver",
 *     sourceIds={@IdField(name="id")},
 *     destinationIds={@IdField(name="identifier", type="string")},
 *     depends={"App\Migrations\DependentMigration"}
 * )
 */
 public class ExampleMigration extends AbstractDataMigration implements DataMigrationInterface
 {
    // Implementation
 }
```

### name
The user-friendly name of the migration.  This should (but isn't necessarily
required to) be unique.

### group
Migrations may be separated into logical groups.  When running migrations,
groups may be used to run only a subset.

### source/destination
Set the source/destination for this migration.  This is a special URL that
drivers parse when setting up the migration. See the
[list of drivers](02_Drivers) for details on valid values.

Common values may be defined in configuration to avoid repeating the same URL.
See [Configuration](03_Configuration.md#page_Sources-and-Destinations) for
details.

### sourceDriver/destinationDriver
*(optional)* If more than one driver implements a given URL scheme, specify the
FQCN of the desired driver here.  If this is omitted, the correct driver will
be guessed based on the URL.

### sourceIds/destinationIds
A list of `@IdField` annotations specifying ids.  This is used to map source
rows to their destinations and allow for updating.  Each `@IdField` has a
`name` field for the name of the source/destination id and an optional `type`
field to specify the data type.  Valid data types are `int` and `string`; the
default is `int`.

### depends
*(optional)* A list of migration FQCNs that must be run before this migration.

### flush
*(optional)* Set this to `true` to flush transformed entities to the destination
immediately, instead of waiting until all entities have been transformed.
The effect this can have depends on the destination driver in use.

### extends
*(optional)* The name of a migration that this logically extends.  This is
uncommon, but may be useful if migrated data references itself and requires
multiple passes to process.  When a migration extends a different migration,
it will share mapper data with the parent migration.  *Note that the parent
migration is not automatically added as a dependency!*  For this to succeed,
the source and destination properties must match.

Configuration
-------------
At the beginning of the migration process, the `configureSource()` and
`configureDestination()` methods are called.  This is where the selected
drivers are configured for running the migration.  For example, if the source
is a database, the source query is set in `configureSource`.

```php
public function configureSource(SourceDriverInterface $sourceDriver)
{
    // Configure the source driver
}

public function configureDestination(DestinationDriverInterface $destinationDriver)
{
    // Configure the destination driver
}
```

See the driver documentation for details on how it must be configured. 

### Default result
If the entity being migrated does not exist in the target, the migration's
`defaultResult()` method is called to retrieve an empty entity.  This could
be an empty array (the default implementation) or a new Doctrine ORM entity.
This default result is then passed to `transform()` to be worked on.

Transformation
--------------
The `transform()` method is where the meat of the migration occurs.

```php
public function transform(array $sourceData, $destinationData)
{
    // Migrate data
}
```

`$sourceData` is a single row from the source driver.  `$destinationData` is
the result as it currently exists in the destination (for updating) or a new
result from `defaultResult()`.

Once the result has been appropriately transformed, it is returned to be
written to the destination.

### Getting data from other migrations
If the migration requires data from other migrations, use the reference store:

```php
$sourceIds = ['id' => $sourceData['reference']];
$referencedEntity = $this->referenceStore->get(OtherMigration::class, $sourceIds);
```

This will read the data written from the given migration (in this example, OtherMigration)
with the given set of source ids.

#### Handling broken references
On occasion, the transformation must reference an entity that does not yet
exist.  Stub entities can be automatically generated to handle this case.
At present, this functionality is only available for the
[DoctrineDestinationDriver](../02_Drivers/02_Destination/DoctrineDestinationDriver.md)
to help with entity relationships.

**NOTE:** This process can seriously impact performance.  It should only be
used as a last resort when dependent migrations are not possible, e.g. an
entity that references other entities of the same type.

To avoid odd bugs in the migration process, set [flush](#page_flush) to `true` in
the `@DataMigration` annotation.  Skipping this step could lead to the stub
and/or entities referencing it not being updated properly when it is migrated.

Pass `true` as the final argument to `referenceStore->get()` to receive a stub
if the entity does not exist in the destination.  This stub will be generated
from that migration's `defaultResult()` method with random data filled in the
non-nullable fields to allow it to be written to the database.

```php
$sourceIds = ['id' => $sourceData['reference']];
$referencedEntity = $this->referenceStore->get(OtherMigration::class, $sourceIds, true);
```

If the transformation logic must behave differently on stubs, check if the
entity id field(s) are set.  Id fields are not automatically filled in by the
stub generation process.

If a stub is created, any created entities will be flushed to the database
immediately.

**NOTE:** An alternative to the stubbing process is to write a
separate migration that acts as a second pass.  Use the [extends](#page_extends)
property in the annotation to declare a migration as an extension.  For this to
succeed, the source and destination properties must match.  Depending on the
data, this may have a less severe performance impact.
