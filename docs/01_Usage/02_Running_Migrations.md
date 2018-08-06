### Run all migrations
Running all migrations is as simple as running a command:
```bash
php bin/console a2b:migrate
```
It's also possible to have more control over the process:

### Run only certain groups
```bash
php bin/console a2b:migrate --group=Special
```
Specify the desired group with `--group`.  For multiple groups, use `--group`
more than once, e.g. `--group=Special --group=Other`

### Run specific migrations
```bash
php bin/console a2b:migrate App\Migrations\SpecialMigration
```
List the FQCN of the desired migrations at the end of the command, separated
with spaces.

### Orphan handling
An orphan is considered a data entity that exists in the destination prior to
the migration, but has no counterpart in the source.  There are many causes
for this, most commonly when the destination is updated manually between
migrations.

The default behavior if any orphans are found when running a migration is to
ask at the end of the migration process.  For each migration orphans may
optionally be kept in the destination, removed from the destination, or
decided on a case-by-case basis.

To configure orphan handling for all migrations that will be run:
- Use `--preserve` to keep all orphans in the destination
- Use `--prune` to remove all orphans from the destination.
