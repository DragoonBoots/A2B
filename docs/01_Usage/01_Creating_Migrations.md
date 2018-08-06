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
