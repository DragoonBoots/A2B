<?php
/**
 * @file Template for making migrations with MakerBundle.
 *
 * @var string                                  $class_name
 * @var string                                  $namespace
 * @var string                                  $name
 * @var string                                  $group
 * @var string                                  $source
 * @var string                                  $source_driver
 * @var IdField[] $source_ids
 * @var string                                  $destination
 * @var string                                  $destination_driver
 * @var IdField[] $destination_ids
 * @var string[]                                $dependencies
 */

use DragoonBoots\A2B\Annotations\IdField;

/**
 * The number of elements an array must have before it is forced to be multiple
 * lines.
 */
const A2B_ARRAY_INLINE_LIMIT = 2;

$GLOBALS['noStringWrap'] = [];

/**
 * Format a string for insertion into an annotation.
 *
 * @param string $string
 *
 * @return string
 */
function format_string(string $string): string
{
    global $noStringWrap;

    if (!in_array($string, $noStringWrap)) {
        $formatted = sprintf('"%s"', $string);
        $noStringWrap[] = $formatted;

        return $formatted;
    } else {
        return $string;
    }
}

/**
 * Format an array for insertion into an annotation.
 *
 * @param array $array
 *
 * @return string
 */
function format_array(array $array): string
{
    global $noStringWrap;

    $parts = [];
    foreach ($array as $item) {
        $parts[] = format_string($item);
    }
    $formatted = sprintf('{%s}', implode(', ', $parts));
    $noStringWrap[] = $formatted;

    return $formatted;
}

/**
 * Format a field for insertion into an annotation.
 *
 * @param string $key
 * @param        $value
 *
 * @return string
 */
function format_field(string $key, $value): string
{
    global $noStringWrap;

    if (is_array($value)) {
        $string = format_array($value);
    } else {
        $string = format_string($value);
    }

    $formatted = sprintf('%s=%s', $key, $string);
    $noStringWrap[] = $formatted;

    return $formatted;
}

/**
 * Format an annotation for display.
 *
 * @param string $annotation
 * @param        $value
 * @param bool   $root
 *
 * @return string
 */
function format_annotation(string $annotation, $value, bool $root = false): string
{
    global $noStringWrap;

    if (is_array($value)) {
        $multiline = $root || count($value) > A2B_ARRAY_INLINE_LIMIT;
        if ($multiline) {
            $valuesPrefix = "\n";
            $valuesSuffix = "\n";
            if ($root) {
                $indent = str_repeat(' ', 4);
            } else {
                $indent = str_repeat(' ', 8);
            }
            $closer = ' * '.str_repeat(' ', strlen($indent) - 4).")\n";
            $prefix = sprintf(' * %s', $indent);
            $suffix = ",\n";
        } else {
            $valuesPrefix = '';
            $valuesSuffix = '';
            $prefix = '';
            $suffix = ', ';
            $closer = ')';
        }
        $parts = [];
        foreach ($value as $fieldKey => $fieldValue) {
            $parts[] = $prefix.format_field($fieldKey, $fieldValue);
        }
        $string = $valuesPrefix.implode($suffix, $parts).$valuesSuffix;
    } else {
        $string = format_string($value);
        $closer = ')';
    }

    $formatted = sprintf('@%s(%s%s', $annotation, $string, $closer);
    $noStringWrap[] = $formatted;

    return $formatted;
}

/**
 * Format a list of id fields for insertion into a annotation.
 *
 * @param array $idFields
 *
 * @return string[]
 */
function format_id_fields(array $idFields): array
{
    $idStrings = [];
    foreach ($idFields as $idField) {
        $idFieldValues['name'] = $idField->getName();
        if ($idField->getType() !== 'int') {
            $idFieldValues['type'] = $idField->getType();
        }

        $idStrings[] = format_annotation('IdField', $idFieldValues);
    }

    return $idStrings;
}

// @formatter:off
?><?= "<?php\n" ?>

namespace <?= $namespace ?>;

use DragoonBoots\A2B\Annotations\DataMigration;
use DragoonBoots\A2B\Annotations\IdField;
use DragoonBoots\A2B\DataMigration\AbstractDataMigration;
use DragoonBoots\A2B\DataMigration\DataMigrationInterface;
use DragoonBoots\A2B\Drivers\DestinationDriverInterface;
use DragoonBoots\A2B\Drivers\SourceDriverInterface;

/**
 * <?= $name ?> migration.
 *
 * <?php
// @formatter:on
$prefix = ' *     ';
$suffix = ",\n";
$fields = [];

$fields['name'] = $name;
if ($group) {
    $fields['group'] = $group;
}
$fields['source'] = $source;
if ($source_driver) {
    $fields['sourceDriver'] = $source_driver;
}
$fields['sourceIds'] = format_id_fields($source_ids);
$fields['destination'] = $destination;
if ($destination_driver) {
    $fields['destinationDriver'] = $destination_driver;
}
$fields['destinationIds'] = format_id_fields($destination_ids);
if ($dependencies) {
    $fields['depends'] = $dependencies;
}
echo format_annotation('DataMigration', $fields, true);
// @formatter:off
?>
 */
class <?= $class_name ?> extends AbstractDataMigration
{

    /**
     * {@inheritdoc}
     * @param SourceDriverInterface $sourceDriver
     */
    public function configureSource(SourceDriverInterface $sourceDriver)
    {
        // TODO: Implement configureSource() method.
    }

    /**
     * {@inheritdoc}
     */
    public function transform($sourceData, $destinationData)
    {
        // TODO: Implement transform() method.

        return $destinationData;
    }

    /**
     * {@inheritdoc}
     * @param DestinationDriverInterface $destinationDriver
     */
    public function configureDestination(DestinationDriverInterface $destinationDriver)
    {
        // TODO: Implement configureDestination() method.
    }
}
