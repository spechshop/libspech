<?php

declare(strict_types=1);

namespace Swoole;


class MultibyteStringObject {

    
    public function length(): \int {
        return 0;
    }

    
    public function indexOf(\string $needle, \int $offset = 0, \string $encoding = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function lastIndexOf(\string $needle, \int $offset = 0, \string $encoding = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function pos(\string $needle, \int $offset = 0, \string $encoding = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function rpos(\string $needle, \int $offset = 0, \string $encoding = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function ipos(\string $needle, \int $offset = 0, \string $encoding = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @see https://www.php.net/mb_substr
     */
    public function substr(\int $start, \int $length = NULL, \string $encoding = NULL): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * {@inheritDoc}
     * @see https://www.php.net/mb_str_split
     */
    public function chunk(\int $length = 1): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }
}
