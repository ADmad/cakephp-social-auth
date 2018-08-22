<?php
/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Database\Type;

use Cake\Database\Driver;
use Cake\Database\Type;
use Cake\Database\TypeInterface;
use PDO;

class SerializeType extends Type implements TypeInterface
{
    /**
     * Convert a value data into a serialized string.
     *
     * @param mixed $value The value to convert.
     * @param \Cake\Database\Driver $driver The driver instance to convert with.
     *
     * @return string|null
     */
    public function toDatabase($value, Driver $driver)
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return serialize($value);
    }

    /**
     * Convert string values to PHP data structure.
     *
     * @param mixed $value The value to convert.
     * @param \Cake\Database\Driver $driver The driver instance to convert with.
     *
     * @return string|null|array
     */
    public function toPHP($value, Driver $driver)
    {
        if ($value === null) {
            return $value;
        }

        return unserialize($value);
    }

    /**
     * Get the correct PDO binding type for string data.
     *
     * @param mixed $value The value being bound.
     * @param \Cake\Database\Driver $driver The driver.
     *
     * @return int
     */
    public function toStatement($value, Driver $driver)
    {
        if ($value === null) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_LOB;
    }

    /**
     * Marshalls request data into a serialization compatible structure.
     *
     * @param mixed $value The value to convert.
     *
     * @return mixed Converted value.
     */
    public function marshal($value)
    {
        if (is_array($value) || $value === null) {
            return $value;
        }

        return $value;
    }
}
