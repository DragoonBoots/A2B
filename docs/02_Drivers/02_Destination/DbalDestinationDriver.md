Introduction
------------
The `DbalDestinationDriver` writes to anything supported by the
[Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/introduction.html#introduction).

Supported URLs
--------------
See [Doctrine's website](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/configuration.html#connecting-using-a-url)
for a list of supported URL formats.

Pass the base table as the URL fragment (e.g. `sqlite:///db.sqlite#table_name`)

Usage
-----
Return an associative array, keyed by column name.

Pay special attention to the id fields when returning the array of data.
*Id fields, even auto-increment columns, must have data present.*  This is
because of limitations in the Dbal preventing the retrieval of the
auto-increment value to use in data mapping.
