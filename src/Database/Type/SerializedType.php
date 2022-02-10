<?php
declare(strict_types=1);

/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Database\Type;

use Cake\Database\DriverInterface;
use Cake\Database\Type\BaseType;
use PDO;

class SerializedType extends BaseType
{
    /**
     * Convert a value data into a serialized string.
     *
     * @param mixed $value The value to convert.
     * @param \Cake\Database\DriverInterface $driver The driver instance to convert with.
     * @return string|null
     */
    public function toDatabase($value, DriverInterface $driver)
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
     * @param \Cake\Database\DriverInterface $driver The driver instance to convert with.
     * @return mixed
     */
    public function toPHP($value, DriverInterface $driver)
    {
        if ($value === null) {
            return $value;
        }

        if (is_resource($value)) {
            $stream = $value;
            $value = stream_get_contents($stream);
            fclose($stream);
        }

        return unserialize($value);
    }

    /**
     * Get the correct PDO binding type for string data.
     *
     * @param mixed $value The value being bound.
     * @param \Cake\Database\DriverInterface $driver The driver.
     * @return int
     */
    public function toStatement($value, DriverInterface $driver)
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
