<?php


namespace DragoonBoots\A2B\Drivers\Destination\Yaml;


use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Symfony\Component\Yaml\Dumper;

class YamlDumper extends Dumper
{

    /**
     * {@inheritdoc}
     * @param array|null $refs
     *   A map of reference names and their values.
     */
    public function dump($input, int $inline = 0, int $indent = 0, int $flags = 0, ?array $refs = null): string
    {
        if (!is_null($refs)) {
            $refKey = 'REF__'.bin2hex(random_bytes(16));
            [$replacements, $placeholderMap] = $this->createReferencePlaceholders($input, $refs, $refKey);

            $yaml = parent::dump($input, $inline, $indent, $flags);

            // Replace the placeholders with actual data
            // Sort by increasing depth to ensure replacements can be found.
            uksort(
                $placeholderMap,
                function (string $a, $b) {
                    return substr_count($a, '.') - substr_count($b, '.');
                }
            );
            $anchorMap = array_flip($placeholderMap);

            do {
                foreach ($replacements as $placeholder => $value) {
                    $anchor = $anchorMap[$placeholder];
                    $value = $replacements[$placeholder];
                    $depth = substr_count($anchor, '.');

                    // Replace the anchor placeholder
                    $replacementIndent = $indent + ($depth * $this->indentation) + $this->indentation;
                    if (is_array($value)) {
                        $yamlValue = rtrim(parent::dump($value, $inline, $replacementIndent, $flags));
                        $replacement = '&'.$anchor."\n".$yamlValue;
                    } else {
                        $yamlValue = trim(
                            parent::dump([$value], $inline, $replacementIndent - $this->indentation, $flags)
                        );
                        $yamlValue = preg_replace('`^\s*- `', '', $yamlValue);
                        $replacement = '&'.$anchor.' '.$yamlValue;
                    }
                    $yaml = str_replace($placeholder.'__ANCHOR', $replacement, $yaml, $anchorReplacementCount);

                    // Replace alias uses
                    $yaml = str_replace($placeholder.'__ALIAS', '*'.$anchor, $yaml, $aliasReplacementCount);
                }
            } while (strpos($yaml, $refKey) !== false);

            return $yaml;
        } else {
            return parent::dump($input, $inline, $indent, $flags);
        }
    }

    /**
     * Create a map of placeholders to use to insert references.
     *
     * @param array $input
     * @param array $refs
     * @param string $refKey
     * @param array|null $path
     * @param array|null $placeholderMap
     *   Maps keys to their placeholders so the anchor/alias can be inserted
     *   after dumping.
     * @param array|null $replacements
     *   Maps placeholders with their actual values.
     *
     * @return array
     * @throws Exception
     */
    protected function createReferencePlaceholders(
        array &$input,
        array $refs,
        string $refKey,
        ?array $path = null,
        ?array &$placeholderMap = null,
        ?array &$replacements = null
    ): array {
        if (!isset($path)) {
            $path = [];
        }
        if (!isset($placeholderMap)) {
            $placeholderMap = [];
        }
        if (!isset($replacements)) {
            $replacements = [];
        }

        foreach ($input as $key => &$item) {
            // Current position in the yaml data
            $itemPath = array_merge($path, [$key]);
            $itemPathKey = implode('.', $itemPath);

            if (isset($refs[$itemPathKey])) {
                // This is the first instance of this value; this item is an
                // anchor.  Add the placeholder to the placeholder map and
                // store the replacement value so it can be re-inserted lated.
                $placeholderMap[$itemPathKey] = $refKey.'__'.sha1($itemPathKey);
                $replacements[$placeholderMap[$itemPathKey]] = $item;
            } else {
                // This may be an alias; the check happens in replaceItemWithPlaceholder
                $item = $this->replaceItemWithPlaceholder($item, $itemPathKey, $refs, $placeholderMap);
            }

            if (is_array($item)) {
                // Recursively check the item for other values that need
                // placeholder replacement.
                $this->createReferencePlaceholders($item, $refs, $refKey, $itemPath, $placeholderMap, $replacements);
                if (isset($refs[$itemPathKey])) {
                    // This is an array with an entry in the replacements table,
                    // check that table for sub-entries that need placeholders.
                    foreach ($replacements[$placeholderMap[$itemPathKey]] as $replacementKey => &$replacementValue) {
                        $replacementValue = $this->replaceItemWithPlaceholder(
                            $replacementValue,
                            $itemPathKey.'.'.$replacementKey,
                            $refs,
                            $placeholderMap
                        );
                    }
                }
            }

            if (isset($refs[$itemPathKey])) {
                // Only replace the value with the anchor placeholder after
                // scanning children.
                $item = $placeholderMap[$itemPathKey].'__ANCHOR';
            }
        }

        return [$replacements, $placeholderMap];
    }

    /**
     * @param            $item
     * @param string $itemPathKey
     * @param array $anchors
     * @param array|null $placeholderMap
     *
     * @return mixed|string
     */
    protected function replaceItemWithPlaceholder(
        $item,
        string $itemPathKey,
        array $anchors,
        ?array &$placeholderMap
    ) {
        $itemPath = new ArrayCollection(explode('.', $itemPathKey));
        // Use the anchor if this is an array or the final key in the key
        // path matches (this means these values are likely similar
        // contextually.
        $useAnchor = false;
        foreach ($anchors as $checkAnchorKey => $checkValue) {
            $anchorPath = new ArrayCollection(explode('.', $checkAnchorKey));
            $this->resolvePlaceholder($item, $placeholderMap, $anchors);
            if ($checkValue === $item && (is_array($item) || $anchorPath->last() === $itemPath->last())) {
                $useAnchor = $checkAnchorKey;
                break;
            }
        }

        if (strpos($useAnchor, $itemPathKey) === 0) {
            $type = 'ANCHOR';
        } else {
            $type = 'ALIAS';
        }
        if ($useAnchor !== false && isset($placeholderMap[$useAnchor])) {
            $item = $placeholderMap[$useAnchor].'__'.$type;
        }

        if (is_array($item)) {
            foreach ($item as $key => &$value) {
                $value = $this->replaceItemWithPlaceholder($value, $itemPathKey.'.'.$key, $anchors, $placeholderMap);
            }
        }

        return $item;
    }

    /**
     * Recursively replace placeholders with their values
     *
     * @param $item
     * @param $placeholderMap
     * @param $anchors
     */
    protected function resolvePlaceholder(&$item, $placeholderMap, $anchors)
    {
        // Return early if this is not a placeholder
        if ((is_string($item) && strpos($item, 'REF__') === false)
            || (!is_string($item) && !is_array($item))) {
            return;
        }

        if (!is_array($item)) {
            $anchorPath = array_search(
                str_replace(
                    [
                        '__ANCHOR',
                        '__ALIAS',
                    ],
                    '',
                    $item
                ),
                $placeholderMap
            );
            if ($anchorPath !== false) {
                $item = $anchors[$anchorPath];
            }
        } else {
            foreach ($item as &$itemRow) {
                $this->resolvePlaceholder($itemRow, $placeholderMap, $anchors);
            }
        }
    }

}
