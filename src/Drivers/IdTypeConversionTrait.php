<?php


namespace DragoonBoots\A2B\Drivers;


use DragoonBoots\A2B\Annotations\IdField;

/**
 * Provides a method to typecast id values to their proper type.
 */
trait IdTypeConversionTrait
{

    /**
     * Perform the necessary typecasting on id value.
     *
     * @param IdField $idField
     * @param         $value
     *
     * @return int|mixed
     */
    protected function resolveIdType(IdField $idField, $value)
    {
        $idType = $idField->getType();
        if ($idType == 'int') {
            $value = (int)$value;
        } elseif ($idType == 'string') {
            $value = (string)$value;
        }

        return $value;
    }
}
