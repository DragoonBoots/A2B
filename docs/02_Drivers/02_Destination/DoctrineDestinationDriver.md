Introduction
------------
The `DoctrineDestinationDriver` writes to [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html)
entities.

Supported URLs
--------------
URLs should be in the format `doctrine://ENTITY_FQCN`.  Use forward slashes in
the FQCN; they will be converted to backslashes internally.

Usage
-----
Be sure to override `defaultResult()` to return a new entity.
```php
/**
 * {@inheritdoc}
 */
public function defaultResult()
{
    return new \App\Entity\Generation();
}
``` 

The default entity manager is used.  The entity manager and repository for the
applicable entity is available with `$destinationDriver->getEm()` and
`$destinationDriver->getRepo()`.

If the application requires multiple entity managers, be sure to configure it:
```php
/**
 * @var EntityManagerInterface
 */
protected $em;

public function __construct(MigrationReferenceStoreInterface $referenceStore, EntityManagerInterface $em)
{
    parent::__construct($referenceStore);
    $this->em = $em;
}

/**
 * {@inheritdoc}
 * @param DoctrineDestinationDriver $destinationDriver
 */
public function configureDestination(DestinationDriverInterface $destinationDriver)
{
    $destinationDriver->setEm($this->em);
}
```
