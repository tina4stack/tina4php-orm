<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

/**
 * Collection of ORM utilities
 */
trait ORMUtility
{
    /**
     * Gets a proper object name for returning back data
     * @param string $name Improper object name
     * @param boolean $camelCase Return the name as camel case
     * @return string Proper object name
     * @tests tina4
     *   assert ("test_name", true) === "testName", "Camel case is broken"
     *   assert ("test_name", false) === "test_name", "Camel case is broken"
     */
    final public function getObjectName(string $name, bool $camelCase = false): string
    {
        if (isset($this->fieldMapping) && !empty($this->fieldMapping)) {
            $flippedFieldMap = array_flip($this->fieldMapping);
            $fieldMap = array_change_key_case($flippedFieldMap, CASE_LOWER);

            if (isset($fieldMap[$name])) {
                return $fieldMap[$name];
            }

            if (isset($flippedFieldMap[$name])) {
                return $flippedFieldMap[$name];
            }

            if (!$camelCase) {
                return $name;
            }

            return $this->camelCase($name);
        }

        if (!$camelCase) {
            return $name;
        }

        return $this->camelCase($name);
    }

    /**
     * Return a camel cased version of the name
     * Delegates to the canonical implementation in \Tina4\Utilities (tina4php-core)
     * @param string $name A field name or object name with underscores
     * @return string Camel case version of the input
     * @see \Tina4\Utility::camelCase()
     */
    final public function camelCase(string $name): string
    {
        if (class_exists('\Tina4\Utilities')) {
            return (new \Tina4\Utilities())->camelCase($name);
        }

        // Fallback for standalone usage without tina4php-core
        if (strpos($name, '_')) {
            $name = str_replace('_', ' ', strtolower($name));
        }

        return lcfirst(str_replace(' ', '', ucwords($name)));
    }
}
