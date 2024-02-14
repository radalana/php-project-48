<?php

namespace Differ\Differ;

use Exception;

use function Functional\sort as funcSort;
use function Differ\Parsers\parse;
use function Differ\Formatters\format;

/**
 * @param array<string, mixed> $data
 * @return array<mixed>
 */
function getChildren(array $data): array
{
    return $data['children'];
}

function isIndexedArray(mixed $value): bool
{
    if (is_array($value)) {
        return array_values($value) === $value;
    }
        return false;
}

/**shows if current data if was added/deleted/changed */
/**
 * @param array<string, mixed> $data
 * @return bool
 */
function isChanged(array $data): bool
{
    return key_exists('differ', $data);
}

function toString(mixed $value): mixed
{

    if (is_bool($value) || is_null($value)) {
        return strtolower(var_export($value, true));
    }
    return $value;
}

function readFromFile(string $pathTofile): string
{
    $data = file_get_contents($pathTofile);
    if ($data === false) {
        throw new Exception('Failure to open/read file!');
    }
    return $data;
}

/**
 * @param array<string> $keys
 * @return array<string>
 */
function sortKeys(array $keys): array
{
    return funcSort($keys, fn($a, $b) => strcmp($a, $b));
}

/**
* @param mixed $a
* @param mixed $b
* @return array<int|string, mixed>
*/
function compare(mixed $a, mixed $b): mixed
{
    $properiesOfa = get_object_vars($a);
    $properiesOfb = get_object_vars($b);
    $unionOfProperties = array_unique(array_merge(array_keys($properiesOfa), array_keys($properiesOfb)));
    $sortedAllKeys = sortKeys($unionOfProperties);
    $data = array_map(
        function (string $key) use ($properiesOfa, $properiesOfb): array {
            //in intersection
            if (in_array($key, array_keys($properiesOfa), true) && in_array($key, array_keys($properiesOfb), true)) {
                if (is_object($properiesOfa[$key]) && (is_object($properiesOfb[$key]))) {//both have complex values
                    $iter = compare($properiesOfa[$key], $properiesOfb[$key]);
                    //с is_object не проходит phpstan
                    if ($properiesOfa[$key] instanceof \stdClass && $properiesOfb[$key] instanceof \stdClass) {
                        return ['key' => $key, 'children' => $iter];
                    } else {
                        return ['key' => $key, ...$iter];
                    }
                } else {//one of value is simple
                    if ($properiesOfa[$key] === $properiesOfb[$key]) {
                        return ['key' => $key, 'value'  => $properiesOfa[$key]];
                    } else {
                        return ['key' => $key,'value' => ['firstFile'  => $properiesOfa[$key],
                        'secondFile'  => $properiesOfb[$key]], 'differ' => 'changed'];
                    }
                }
            } elseif (in_array($key, array_keys($properiesOfa), true)) {//only in first filr
                return ['key' => $key, 'value' => ($properiesOfa[$key]), 'differ' => 'deleted'];
            } else {//only in second file
                return ['key' => $key, 'value' => ($properiesOfb[$key]), 'differ' => 'added'];
            }
        },
        $sortedAllKeys
    );
    return $data;
}
/**
 * @param array<string, mixed> $data
 * @return bool
 */
function hasChildren(array $data): bool
{
    return key_exists('children', $data);
}
/**
 * @param array<string, mixed> $data
 * @param string $oldNew
 * @return mixed
 */
function getValue(array $data, string $oldNew = ''): mixed
{
    if ($oldNew === '') {
        return $data['value'];
    }
    return $oldNew === 'old' ? $data['value']['firstFile'] : $data['value']['secondFile'];
}

function getFilesType(string $path1, string $path2): string
{
    $type1 = pathinfo($path1, PATHINFO_EXTENSION);
    $type2 = pathinfo($path2, PATHINFO_EXTENSION);
    if ($type1 !== $type2) {
        throw new \Exception('Files must be of the same type!');
    }
    return $type1;
}
function gendiff(string $path1, string $path2, string $formatName = 'stylish'): string
{
    $stringData1 = readFromFile($path1);
    $stringData2 = readFromFile($path2);

    $type = getFilesType($path1, $path2);
    $data1 = parse($stringData1, $type);
    $data2 = parse($stringData2, $type);

    $ast = compare($data1, $data2);
    return format($formatName, $ast);
}
