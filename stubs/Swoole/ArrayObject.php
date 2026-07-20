<?php

declare(strict_types=1);

namespace Swoole;


class ArrayObject {

    /**
     * ArrayObject constructor.
     */
    public function __construct(\array $array = []) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function __toArray(): \array {
        return [];
    }

    
    public function __serialize(): \array {
        return [];
    }

    
    public function __unserialize(\array $data) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public static function from(\array $array = []): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function toArray(): \array {
        return [];
    }

    
    public function isEmpty(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function count(): \int {
        return 0;
    }

    /**
     * @return mixed
     */
    public function current() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function key() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function valid(): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function rewind() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function next() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return ArrayObject|StringObject
     */
    public function get($key) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return ArrayObject|StringObject
     */
    public function getOr($key, $default = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function last() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return int|string|null
     */
    public function firstKey() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return int|string|null
     */
    public function lastKey() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function first() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function set($key, $value): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function delete($key): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function remove($value, \bool $strict = true, \bool $loop = false): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function clear(): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($key) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetSet($key, $value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function offsetUnset($key) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return bool
     */
    public function offsetExists($key) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function exists($key): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function contains($value, \bool $strict = true): \mixed {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function indexOf($value, \bool $strict = true) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function lastIndexOf($value, \bool $strict = true) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function search($needle, \bool $strict = true) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function join(\string $glue = ''): \Swoole\StringObject {
        return class_exists(\Swoole\StringObject::class) ? \Swoole\StringObject::class : \stdClass::class;
    }

    
    public function serialize(): \string {
        return "";
    }

    
    public function unserialize(\Stringable|Swoole\StringObject|string $string): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return float|int
     */
    public function sum() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return float|int
     */
    public function product() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return int
     */
    public function push($value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return int
     */
    public function pushFront($value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function append($values = NULL): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return int
     */
    public function pushBack($value) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function insert(\int $offset, $value): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function pop() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function popFront() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @return mixed
     */
    public function popBack() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function slice(\int $offset, \int $length = NULL, \bool $preserve_keys = false): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * @return ArrayObject|mixed|StringObject
     */
    public function randomGet() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function each(\callable $fn): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @param array $args
     */
    public function map(\callable $fn, $args = NULL): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * @param null $initial
     * @return mixed
     */
    public function reduce(\callable $fn, $initial = NULL) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    /**
     * @param array $args
     */
    public function keys($args = NULL): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function values(): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function column($column_key, $index = NULL): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function unique(\int $sort_flags = 2): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function reverse(\bool $preserve_keys = false): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function chunk(\int $size, \bool $preserve_keys = false): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * Swap keys and values in an array.
     */
    public function flip(): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    
    public function filter(\callable $fn, \int $flag = 0): \static {
        return class_exists(\static::class) ? \static::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function asort(\int $sort_flags = 0): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function arsort(\int $sort_flags = 0): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function krsort(\int $sort_flags = 0): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function ksort(\int $sort_flags = 0): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function natcasesort(): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function natsort(): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    /**
     * @return $this
     */
    public function rsort(\int $sort_flags = 0): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function shuffle(): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function sort(\int $sort_flags = 0): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function uasort(\callable $value_compare_func): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function uksort(\callable $value_compare_func): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }

    
    public function usort(\callable $value_compare_func): \Swoole\ArrayObject {
        return class_exists(\Swoole\ArrayObject::class) ? \Swoole\ArrayObject::class : \stdClass::class;
    }
}
