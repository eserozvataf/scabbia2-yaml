<?php
/**
 * Scabbia2 Yaml Component
 * https://github.com/eserozvataf/scabbia2
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link        https://github.com/eserozvataf/scabbia2-yaml for the canonical source repository
 * @copyright   2010-2016 Eser Ozvataf. (http://eser.ozvataf.com/)
 * @license     http://www.apache.org/licenses/LICENSE-2.0 - Apache License, Version 2.0
 *
 * -------------------------
 * Portions of this code are from Symfony YAML Component under the MIT license.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE-MIT
 * file that was distributed with this source code.
 *
 * Modifications made:
 * - Scabbia Framework code styles applied.
 * - All dump methods are moved under Dumper class.
 * - Redundant classes removed.
 * - Namespace changed.
 * - Tests ported to Scabbia2.
 * - Encoding checks removed.
 */

namespace Scabbia\Yaml;

use Scabbia\Yaml\Inline;

/**
 * Dumper dumps PHP variables to YAML strings
 *
 * @package     Scabbia\Yaml
 * @author      Fabien Potencier <fabien@symfony.com>
 * @author      Eser Ozvataf <eser@ozvataf.com>
 * @since       2.0.0
 */
class Dumper
{
    /**
     * Dumps a PHP value to YAML
     *
     * @param mixed   $input                  The PHP value
     * @param int     $inline                 The level where you switch to inline YAML
     * @param int     $indentation            The level of indentation (used internally)
     *
     * @return string  The YAML representation of the PHP value
     */
    public static function dump($input, $inline = 0, $indentation = 0)
    {
        $output = "";
        $prefix = $indentation ? str_repeat(" ", $indentation) : "";

        if ($inline <= 0 || !is_array($input) || count($input) === 0) {
            $output .= $prefix . self::dumpInline($input);
        } else {
            $isAHash = array_keys($input) !== range(0, count($input) - 1);

            foreach ($input as $key => $value) {
                $willBeInlined = (($inline - 1 <= 0) || !is_array($value) || count($value) === 0);

                $output .= $prefix .
                    ($isAHash ? self::dumpInline($key) . ":" : "-") .
                    ($willBeInlined ? " " : "\n") .
                    self::dump($value, $inline - 1, ($willBeInlined ? 0 : $indentation)) .
                    ($willBeInlined ? "\n" : "");
            }
        }

        return $output;
    }

    /**
     * Dumps a given PHP variable to a YAML string
     *
     * @param mixed   $value                  The PHP variable to convert
     *
     * @return string The YAML string representing the PHP array
     */
    public static function dumpInline($value)
    {
        if (is_resource($value)) {
            return "null";
        } elseif (is_object($value)) {
            return "!!php/object:" . serialize($value);
        } elseif (is_array($value)) {
            return self::dumpInlineArray($value);
        } elseif ($value === null) {
            return "null";
        } elseif ($value === true) {
            return "true";
        } elseif ($value === false) {
            return "false";
        } elseif (ctype_digit($value)) {
            return is_string($value) ? "\"$value\"" : (int)$value;
        } elseif (is_numeric($value)) {
            $locale = setlocale(LC_NUMERIC, 0);
            if ($locale !== false) {
                setlocale(LC_NUMERIC, "C");
            }

            if (is_float($value)) {
                $repr = (string)$value;

                if (is_infinite($value)) {
                    $repr = str_ireplace("INF", ".Inf", $repr);
                } elseif (floor($value) == $value && $repr == $value) {
                    // Preserve float data type since storing a whole number will result in integer value.
                    $repr = "!!float {$repr}";
                }
            } elseif (is_string($value)) {
                $repr = "'$value'";
            } else {
                $repr = (string)$value;
            }

            if ($locale !== false) {
                setlocale(LC_NUMERIC, $locale);
            }

            return $repr;
        } elseif ($value === "") {
            return "''";
        } elseif (strstr($value, "\n") !== false) {
            return "|\n  " . preg_replace("/\\n/", "\n  ", $value);
        } elseif (Escaper::requiresDoubleQuoting($value)) {
            return Escaper::escapeWithDoubleQuotes($value);
        } elseif (Escaper::requiresSingleQuoting($value) ||
            preg_match(Inline::getHexRegex(), $value) ||
            preg_match(Inline::getTimestampRegex(), $value)) {
            return Escaper::escapeWithSingleQuotes($value);
        } else {
            return $value;
        }
    }

    /**
     * Dumps a PHP array to a YAML string
     *
     * @param array   $value                  The PHP array to dump
     *
     * @return string The YAML string representing the PHP array
     */
    protected static function dumpInlineArray(array $value)
    {
        // array
        $keys = array_keys($value);
        $keysCount = count($keys);
        $func = function ($v, $w) {
            return (int)$v + $w;
        };

        if (($keysCount === 1 && $keys[0] === "0") ||
            ($keysCount > 1 && array_reduce($keys, $func, 0) == $keysCount * ($keysCount - 1) / 2)
        ) {
            $output = [];
            foreach ($value as $val) {
                $output[] = self::dumpInline($val);
            }

            return "[" . implode(", ", $output) . "]";
        }

        // mapping
        $output = [];
        foreach ($value as $key => $val) {
            $output[] = self::dumpInline($key) . ": " . self::dumpInline($val);
        }

        return "{ " . implode(", ", $output) . " }";
    }
}
