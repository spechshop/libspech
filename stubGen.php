<?php

function safeExport($v)
{
    return str_replace('array (', '[', str_replace("\n)", "]", var_export($v, true)));
}

function writeStubFile($namespace, $className, $code)
{
    $dir = __DIR__ . '/stubs/' . str_replace('\\', '/', $namespace);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/{$className}.php", $code);
}


function generateFunctionStubs(string $ext)
{
    $parts = getExplode($ext);
}


function generateExtensionConstants(string $ext)
{
    extracted($ext);
}

function generateClassStubs(array $allowFilters)
{
    $nsParts = getExplode1($allowFilters);
}

// 🔧 Qual extensão você quer gerar stub
generateFunctionStubs('bcg729');
generateFunctionStubs('opus');
generateFunctionStubs('psampler');

generateExtensionConstants('bcg729');
generateExtensionConstants('opusChannel');
generateExtensionConstants('psampler');

// 🔧 Filtrar classes permitidas
generateClassStubs(['bcg729', 'lpcm', 'bcg729Channel', 'opusChannel', 'psampler']);

function listStubFolders($dir = __DIR__ . '/stubs')
{
    if (!is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            echo $path . "\n";

        }
    }
}

// List generated stub folders
listStubFolders();

