<?php

namespace DragoonBoots\A2B\Drivers\Destination;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\Driver;
use DragoonBoots\A2B\Drivers\AbstractDestinationDriver;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Exception\BadUriException;
use League\Uri\Parser;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Doctrine ORM entity Destination driver
 *
 * @Driver(schemes="doctrine", supportsStubs=true)
 */
class DoctrineDestinationDriver extends AbstractDestinationDriver implements DestinationDriverInterface
{

    /**
     * The entity manager to use during a specific migration.
     *
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * The default entity manager
     *
     * @var EntityManagerInterface
     */
    protected $defaultEm;

    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccess;

    /**
     * @var ObjectRepository
     */
    private $repo;

    /**
     * A count of the entities persisted in this pass.
     *
     * @var int
     */
    private $persistedCount = 0;

    /**
     * DoctrineDestinationDriver constructor.
     *
     * @param Parser                    $uriParser
     * @param EntityManagerInterface    $em
     * @param PropertyAccessorInterface $propertyAccess
     */
    public function __construct(Parser $uriParser, EntityManagerInterface $em, PropertyAccessorInterface $propertyAccess)
    {
        parent::__construct($uriParser);

        $this->defaultEm = $em;
        $this->propertyAccess = $propertyAccess;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(DataMigration $definition)
    {
        parent::configure($definition);

        // Replace forward slashes with back slashes in FQCN.
        $dest = $this->destUri['path'];
        $dest = str_replace('/', '\\', $dest);
        $this->destUri['path'] = $dest;

        if (!class_exists($dest)) {
            throw new BadUriException($definition->getDestination());
        }

        // Reset to defaults
        $this->em = $this->defaultEm;
        $this->repo = null;
        $this->persistedCount = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getExistingIds(): array
    {
        $ids = [];
        foreach ($this->getRepo()->findAll() as $entity) {
            $id = [];
            foreach ($this->destIds as $destId) {
                $idName = $destId->getName();
                $id[$idName] = $this->resolveIdType($destId, $this->propertyAccess->getValue($entity, $idName));
            }
            $ids[] = $id;
        }
        unset($entity);

        return $ids;
    }

    /**
     * Get the proper entity repository.
     *
     * @return ObjectRepository
     */
    public function getRepo()
    {
        if (!isset($this->repo)) {
            $entityType = $this->destUri['path'];
            $this->repo = $this->em->getRepository($entityType);
        }

        return $this->repo;
    }

    /**
     * Get the entity as last migrated from the destination for updating.
     *
     * @param array $destIds
     *   A list of key-value pairs where the key is the destination id field and
     *   the value is destination id value.
     *
     * @return mixed|null
     *   Returns the selected entity, or null if it does not exist in the
     *   destination.
     */
    public function read(array $destIds)
    {
        return $this->getRepo()->findOneBy($destIds);
    }

    /**
     * Write the transformed data.
     *
     * @param $data
     *
     * @return array|null
     *   An associative array with the destination keys.  If no keys can
     *   logically exist (e.g. output only), return null.
     */
    public function write($data)
    {
        $this->em->persist($data);
        $this->persistedCount++;
        if ($this->persistedCount % 100 == 0) {
            // Avoids out-of-memory errors when persisting a large number of
            // entities at once.
            $this->em->flush();
        }

        $id = [];
        foreach ($this->destIds as $destId) {
            $idName = $destId->getName();
            $id[$idName] = $this->resolveIdType($destId, $this->propertyAccess->getValue($data, $idName));
        }

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->em->flush();
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEm(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     * @param EntityManagerInterface $em
     *
     * @return self
     */
    public function setEm(EntityManagerInterface $em): self
    {
        $this->em = $em;
        // Force getting the repo from the new entity manager.
        $this->repo = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function freeMemory(): void
    {
        parent::freeMemory();

        $this->em->flush();
    }
}
