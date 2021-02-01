Doctrine ORM
============

Introduction
------------

The ``DoctrineDestinationDriver`` writes to `Doctrine ORM <https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html>`_
entities.

Usage
-----

Use the FQCN of the result entity class as the ``destination``.

Be sure to override ``defaultResult()`` to return a new entity.

.. code-block:: php

   <?php

   /**
    * {@inheritdoc}
    */
   public function defaultResult()
   {
       return new \App\Entity\Generation();
   }

The default entity manager is used. The entity manager and repository
for the applicable entity is available with
``$destinationDriver->getEm()`` and ``$destinationDriver->getRepo()``.

If the application requires multiple entity managers, be sure to declare
the entity manager your migration requires in the service configuration:

.. code-block:: php

   <?php

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
