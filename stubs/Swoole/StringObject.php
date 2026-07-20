<?php

declare(strict_types=1);

namespace Swoole;


class StringObject {

    /**
     * StringObject constructor.
     */
    public function __construct(\string $string = '') {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __toString(): \string {
        return "";
    }

    
    public static function from(\string $string = ''): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function length(): \int {
        return 0;
    }

    
    public function indexOf(\string $needle, \int $offset = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function lastIndexOf(\string $needle, \int $offset = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pos(\string $needle, \int $offset = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rpos(\string $needle, \int $offset = 0) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function reverse(): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * @return false|int
     */
    public function ipos(\string $needle) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function lower(): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function upper(): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function trim(\string $characters = ''): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * @return static
     */
    public function ltrim(): \Swoole\StringObject {
        return class_exists(\Swoole\StringObject::class) ? \Swoole\StringObject::class : \stdClass::class;
    }

    /**
     * @return static
     */
    public function rtrim(): \Swoole\StringObject {
        return class_exists(\Swoole\StringObject::class) ? \Swoole\StringObject::class : \stdClass::class;
    }

    /**
     * @return static
     */
    public function substr(\int $offset, \int $length = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function repeat(\int $times): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function append($str): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * @param int|null $count
     */
    public function replace(\string $search, \string $replace, &$count = NULL): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function startsWith(\string $needle): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function endsWith(\string $needle): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function equals($str, \bool $strict = false): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function contains(\string $subString): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function split(\string $delimiter, \int $limit = 9223372036854775807): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function char(\int $index): \string {
        return "";
    }

    /**
     * Get a new string object by splitting the string of current object into smaller chunks.
     *
     * @param int $length The chunk length.
     * @param string $separator The line ending sequence.
     * @see https://www.php.net/chunk_split
     */
    public function chunkSplit(\int $length = 76, \string $separator = '
'): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * Convert a string to an array object of class \Swoole\ArrayObject.
     *
     * @param int $length Maximum length of the chunk.
     * @see https://www.php.net/str_split
     */
    public function chunk(\int $length = 1): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function toString(): \string {
        return "";
    }
}
