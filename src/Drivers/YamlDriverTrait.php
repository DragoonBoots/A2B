<?php


namespace DragoonBoots\A2B\Drivers;

use DragoonBoots\A2B\Annotations\IdField;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Helper methods for drivers that use YAML files.
 */
trait YamlDriverTrait
{

    use IdTypeConversionTrait;

    /**
     * Add the given ids back to the entity
     *
     * Ids are removed from the entity when it is saved (as this information is
     * now stored in the path), so they need to be added back to the entity.
     *
     * @param array $ids
     * @param array $entity
     *
     * @return array
     */
    protected function addIdsToEntity(array $ids, array $entity)
    {
        foreach ($ids as $id => $value) {
            $entity[$id] = $value;
        }

        return $entity;
    }

    /**
     * Get id field values from the file path.
     *
     * The Id is built as in this example:
     * - Id fields: a, b, c
     * - Path: w/x/y/z.yaml
     * - Result: a=x, b=y, c=z (note that w is ignored because there are only
     *   3 id fields.
     *
     * @param SplFileInfo $fileInfo
     * @param array       $ids
     *
     * @return array
     */
    protected function buildIdsFromFilePath(\SplFileInfo $fileInfo, array $ids): array
    {
        $pathParts = explode('/', $fileInfo->getPath());
        $pathParts[] = $fileInfo->getBasename('.'.$fileInfo->getExtension());

        $id = [];
        foreach (array_reverse($ids) as $idField) {
            /** @var IdField $idField */
            $id[$idField->getName()] = $this->resolveIdType($idField, array_pop($pathParts));
        }

        return $id;
    }

    /**
     * Build the file path an entity with the given ids will be stored at.
     *
     * @param array  $ids
     * @param string $path
     * @param string $ext
     *   File extension to use.  Defaults to "yaml".
     *
     * @return string
     */
    protected function buildFilePathFromIds(array $ids, string $path, string $ext = 'yaml'): string
    {
        $pathParts = [];
        foreach ($ids as $id => $value) {
            $pathParts[] = $value;
        }

        $fileName = array_pop($pathParts).'.'.$ext;
        $filePath = sprintf('%s/%s/%s', $path, implode('/', $pathParts), $fileName);

        return $filePath;
    }
}
