Introduction
------------
The `DbalSourceDriver` supports using anything supported by the
[Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/introduction.html#introduction)
as a source.

Supported URLs
--------------
See [Doctrine's website](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/configuration.html#connecting-using-a-url)
for a list of supported URL formats.

Usage
-----
In the migration's `configureSource`, two steps are required.  For the main
source data set, create a Doctrine DBAL `Statement` object and pass it to
`setStatement`.  For fetching the count, pass a `Statement` to
`setCountStatement`.

```php
public function configureSource(SourceDriverInterface $sourceDriver)
{
    $statement = $sourceDriver->getConnection()->prepare(
        <<<SQL
SELECT
    "generations"."id",
    "generation_names"."name",
    "regions"."identifier" AS "main_region"
FROM "generations"
    JOIN "generation_names" ON "generations"."id" = "generation_names"."generation_id"
    JOIN "regions" ON "generations"."main_region_id" = "regions"."id"
SQL
    );
    $sourceDriver->setStatement($statement);

    $countStatement = $sourceDriver->getConnection()->prepare(
        <<<SQL
SELECT
    count(*)
FROM "generations"
SQL
    );
    $sourceDriver->setCountStatement($countStatement);
}
```
