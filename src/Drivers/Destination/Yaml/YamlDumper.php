<?php


namespace DragoonBoots\A2B\Drivers\Destination\Yaml;


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
            list($replacements, $placeholderMap) = $this->createReferencePlaceholders($input, $refs, $refKey);

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
                        $yamlValue = trim(parent::dump([$value], $inline, $replacementIndent - $this->indentation, $flags));
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
     * @param array      $input
     * @param array      $refs
     * @param string     $refKey
     * @param array|null $path
     * @param array|null $placeholderMap
     * @param array|null $replacements
     *
     * @return array
     * @throws \Exception
     */
    protected function createReferencePlaceholders(array &$input, array $refs, string $refKey, ?array $path = null, ?array &$placeholderMap = null, ?array &$replacements = null)
    {
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
                $placeholderMap[$itemPathKey] = $refKey.'__'.sha1($itemPathKey);
                $replacements[$placeholderMap[$itemPathKey]] = $item;
            } else {
                $item = $this->replaceItemWithPlaceholder($item, $itemPathKey, $refs, $placeholderMap);
            }

            if (is_array($item)) {
                $this->createReferencePlaceholders($item, $refs, $refKey, $itemPath, $placeholderMap, $replacements);
                if (isset($refs[$itemPathKey])) {
                    // This is an array with an entry in the replacements table,
                    // check that table for sub-entries that need placeholders.
                    foreach ($replacements[$placeholderMap[$itemPathKey]] as $replacementKey => &$replacementValue) {
                        $replacementValue = $this->replaceItemWithPlaceholder($replacementValue, $itemPathKey.'.'.$replacementKey, $refs, $placeholderMap);
                    }
                }
            }

            if (isset($refs[$itemPathKey])) {
                // Only replace with the anchor placeholder after scanning children.
                $item = $placeholderMap[$itemPathKey].'__ANCHOR';
            }
        }

        return [$replacements, $placeholderMap];
    }

    /**
     * @param            $item
     * @param string     $itemPathKey
     * @param array      $refs
     * @param array|null $placeholderMap
     *
     * @return mixed|string
     */
    protected function replaceItemWithPlaceholder($item, string $itemPathKey, array $refs, ?array &$placeholderMap)
    {
        $anchor = array_search($item, $refs, true);
        if (strpos($anchor, $itemPathKey) === 0) {
            $type = 'ANCHOR';
        } else {
            $type = 'ALIAS';
        }
        if ($anchor !== false && isset($placeholderMap[$anchor])) {
            $item = $placeholderMap[$anchor].'__'.$type;
        }

        if (is_array($item)) {
            foreach ($item as $key => &$value) {
                $value = $this->replaceItemWithPlaceholder($value, $itemPathKey.'.'.$key, $refs, $placeholderMap);
            }
        }

        return $item;
    }

}
