<?php


namespace DragoonBoots\A2B\DataMigration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class Stubber implements StubberInterface
{

    /**
     * @var PropertyAccessor
     */
    protected $propertyAccess;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * Stubber constructor.
     *
     * @param PropertyAccessor       $propertyAccess
     * @param EntityManagerInterface $em
     */
    public function __construct(PropertyAccessor $propertyAccess, EntityManagerInterface $em)
    {
        $this->propertyAccess = $propertyAccess;
        $this->em = $em;
    }

    public function createStub(string $migrationId)
    {
        $stub = call_user_func([$migrationId, 'defaultResult']);
        $meta = $this->em->getClassMetadata(get_class($stub));

        $fieldNames = array_diff($meta->getFieldNames(), $meta->getIdentifierFieldNames());
        foreach ($fieldNames as $fieldName) {
            $mapping = $meta->getFieldMapping($fieldName);
            if (!$mapping['nullable']) {
                $meta->setFieldValue($stub, $fieldName, mt_rand());
            }
        }

        return $stub;
    }
}
