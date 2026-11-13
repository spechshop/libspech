<?php

declare(strict_types=1);

// Usage: php stubGen.php bcg729 opus psampler
//        php stubGen.php bcg729,opus,psampler

function stubType(?ReflectionType $type): string
{
    if ($type === null) {
        return '';
    }
    if ($type instanceof ReflectionNamedType) {
        $name = $type->getName();
        if (!$type->isBuiltin() && !in_array(strtolower($name), ['self', 'parent', 'static'], true)) {
            $name = '\\' . ltrim($name, '\\');
        }
        return $type->allowsNull() && !in_array(strtolower($name), ['mixed', 'null'], true)
            ? '?' . $name : $name;
    }
    $parts = [];
    foreach ($type->getTypes() as $part) {
        $rendered = stubType($part);
        $parts[] = $type instanceof ReflectionUnionType && $part instanceof ReflectionIntersectionType
            ? '(' . $rendered . ')' : $rendered;
    }
    return implode($type instanceof ReflectionIntersectionType ? '&' : '|', $parts);
}

function stubValue(mixed $value): ?string
{
    return is_object($value) || is_resource($value) ? null : var_export($value, true);
}

function stubParameters(ReflectionFunctionAbstract $function): string
{
    $parameters = [];
    foreach ($function->getParameters() as $parameter) {
        $type = stubType($parameter->getType());
        $part = ($type === '' ? '' : $type . ' ')
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . $parameter->getName();
        if (!$parameter->isVariadic() && $parameter->isOptional()) {
            $default = null;
            if ($parameter->isDefaultValueAvailable()) {
                try {
                    $default = stubValue($parameter->getDefaultValue());
                } catch (ReflectionException) {
                    // Some internal parameters report an optional value but cannot expose it.
                }
            }
            $part .= $default === null
                ? ' = \\__STUBGEN_DEFAULT_UNAVAILABLE__ /* valor padrão não exposto pela extensão */'
                : ' = ' . $default;
        }
        $parameters[] = $part;
    }
    return implode(', ', $parameters);
}

function stubDocComment(Reflector $reflector, string $indent = ''): string
{
    $comment = $reflector->getDocComment();
    return $comment === false ? '' : $indent . str_replace("\n", "\n" . $indent, $comment) . "\n";
}

function stubHeader(string $namespace): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\n"
        . ($namespace === '' ? '' : "namespace $namespace;\n\n");
}

function stubFunctions(ReflectionExtension $extension): array
{
    $groups = [];
    foreach ($extension->getFunctions() as $function) {
        $name = $function->getName();
        $position = strrpos($name, '\\');
        $namespace = $position === false ? '' : substr($name, 0, $position);
        $shortName = $position === false ? $name : substr($name, $position + 1);
        $return = stubType($function->getReturnType());
        $groups[$namespace][] = stubDocComment($function)
            . 'function ' . ($function->returnsReference() ? '&' : '') . $shortName
            . '(' . stubParameters($function) . ')'
            . ($return === '' ? '' : ': ' . $return) . " {}\n";
    }
    $files = [];
    foreach ($groups as $namespace => $declarations) {
        $path = ($namespace === '' ? '' : str_replace('\\', '/', $namespace) . '/') . 'functions.php';
        $files[$path] = stubHeader($namespace) . implode("\n", $declarations);
    }
    return $files;
}

function stubConstants(ReflectionExtension $extension): array
{
    $groups = [];
    foreach ($extension->getConstants() as $name => $value) {
        $exported = stubValue($value);
        if ($exported === null) {
            continue;
        }
        $position = strrpos($name, '\\');
        $namespace = $position === false ? '' : substr($name, 0, $position);
        $shortName = $position === false ? $name : substr($name, $position + 1);
        $groups[$namespace][] = "const $shortName = $exported;";
    }
    $files = [];
    foreach ($groups as $namespace => $declarations) {
        $path = ($namespace === '' ? '' : str_replace('\\', '/', $namespace) . '/') . 'constants.php';
        $files[$path] = stubHeader($namespace) . implode("\n", $declarations) . "\n";
    }
    return $files;
}

function stubClass(ReflectionClass $class): string
{
    $code = stubHeader($class->getNamespaceName()) . stubDocComment($class);
    if ($class->isInterface()) {
        $kind = 'interface';
    } elseif ($class->isTrait()) {
        $kind = 'trait';
    } elseif ($class->isEnum()) {
        $kind = 'enum';
    } else {
        $kind = ($class->isAbstract() ? 'abstract ' : '')
            . ($class->isFinal() ? 'final ' : '')
            . (method_exists($class, 'isReadOnly') && $class->isReadOnly() ? 'readonly ' : '')
            . 'class';
    }
    $code .= $kind . ' ' . $class->getShortName();
    if ($class->isEnum()) {
        $backingType = (new ReflectionEnum($class->getName()))->getBackingType();
        if ($backingType !== null) {
            $code .= ': ' . stubType($backingType);
        }
    } elseif (!$class->isInterface() && !$class->isTrait()) {
        $parent = $class->getParentClass();
        if ($parent !== false) {
            $code .= ' extends \\' . $parent->getName();
        }
    }
    if (!$class->isTrait()) {
        $interfaces = $class->getInterfaceNames();
        if (!$class->isInterface()) {
            $parent = $class->getParentClass();
            if ($parent !== false) {
                $interfaces = array_diff($interfaces, $parent->getInterfaceNames());
            }
        }
        if ($interfaces !== []) {
            $code .= ($class->isInterface() ? ' extends ' : ' implements ')
                . implode(', ', array_map(static fn (string $name): string => '\\' . $name, $interfaces));
        }
    }
    $code .= "\n{\n";

    foreach ($class->getTraitNames() as $trait) {
        $code .= "    use \\$trait;\n";
    }
    foreach ($class->getReflectionConstants() as $constant) {
        if ($constant->getDeclaringClass()->getName() !== $class->getName()) {
            continue;
        }
        if ($constant instanceof ReflectionEnumUnitCase) {
            $value = $constant instanceof ReflectionEnumBackedCase
                ? ' = ' . var_export($constant->getBackingValue(), true) : '';
            $code .= "    case {$constant->getName()}$value;\n";
            continue;
        }
        $value = stubValue($constant->getValue());
        if ($value === null) {
            continue;
        }
        $visibility = $constant->isPrivate() ? 'private' : ($constant->isProtected() ? 'protected' : 'public');
        $type = method_exists($constant, 'getType') ? stubType($constant->getType()) : '';
        $code .= stubDocComment($constant, '    ')
            . '    ' . ($constant->isFinal() ? 'final ' : '') . $visibility . ' const '
            . ($type === '' ? '' : $type . ' ') . $constant->getName() . " = $value;\n";
    }
    foreach ($class->getProperties() as $property) {
        if ($property->getDeclaringClass()->getName() !== $class->getName()) {
            continue;
        }
        $visibility = $property->isPrivate() ? 'private' : ($property->isProtected() ? 'protected' : 'public');
        $type = stubType($property->getType());
        // Internal classes can expose untyped readonly properties, which PHP source cannot declare.
        if ($property->isReadOnly() && $type === '') {
            $type = 'mixed';
        }
        $code .= stubDocComment($property, '    ') . '    ' . $visibility . ' '
            . ($property->isStatic() ? 'static ' : '')
            . ($property->isReadOnly() ? 'readonly ' : '')
            . ($type === '' ? '' : $type . ' ') . '$' . $property->getName();
        if (!$property->isReadOnly() && $property->hasDefaultValue()) {
            $value = stubValue($property->getDefaultValue());
            if ($value !== null) {
                $code .= ' = ' . $value;
            }
        }
        $code .= ";\n";
    }
    foreach ($class->getMethods() as $method) {
        if ($method->getDeclaringClass()->getName() !== $class->getName()) {
            continue;
        }
        if ($class->isEnum() && in_array($method->getName(), ['cases', 'from', 'tryFrom'], true)) {
            continue;
        }
        $visibility = $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public');
        $return = stubType($method->getReturnType());
        $code .= "\n" . stubDocComment($method, '    ')
            . '    ' . ($method->isFinal() ? 'final ' : '')
            . ($method->isAbstract() && !$class->isInterface() ? 'abstract ' : '')
            . $visibility . ' ' . ($method->isStatic() ? 'static ' : '')
            . 'function ' . ($method->returnsReference() ? '&' : '') . $method->getName()
            . '(' . stubParameters($method) . ')'
            . ($return === '' ? '' : ': ' . $return)
            . ($class->isInterface() || $method->isAbstract() ? ";\n" : " {}\n");
    }
    return $code . "}\n";
}

function removeStubDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

function generateExtensionStubs(string $name, string $root): void
{
    if (!extension_loaded($name)) {
        throw new RuntimeException("Extensão $name não está carregada no PHP atual.");
    }
    $extension = new ReflectionExtension($name);
    $extensionName = $extension->getName();
    if (!preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/D', $extensionName)) {
        throw new RuntimeException("Nome de extensão inválido para diretório: $extensionName");
    }
    $files = stubFunctions($extension) + stubConstants($extension);
    foreach ($extension->getClassNames() as $className) {
        $class = new ReflectionClass($className);
        if (strcasecmp($className, $class->getName()) !== 0) {
            continue; // An alias of an already declared class.
        }
        $path = ($class->getNamespaceName() === '' ? '' : str_replace('\\', '/', $class->getNamespaceName()) . '/')
            . $class->getShortName() . '.php';
        $files[$path] = stubClass($class);
    }
    $target = $root . '/' . $extensionName;
    $staging = $root . '/.stubgen-' . bin2hex(random_bytes(8));
    if (!mkdir($staging, 0777, true)) {
        throw new RuntimeException("Não foi possível criar $staging");
    }
    try {
        foreach ($files as $path => $content) {
            $file = $staging . '/' . $path;
            $directory = dirname($file);
            if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
                throw new RuntimeException("Não foi possível criar $directory");
            }
            if (file_put_contents($file, $content) === false) {
                throw new RuntimeException("Não foi possível escrever $file");
            }
        }
        removeStubDirectory($target);
        if (!rename($staging, $target)) {
            throw new RuntimeException("Não foi possível mover os stubs para $target");
        }
    } finally {
        removeStubDirectory($staging);
    }
    echo "$extensionName: " . count($files) . " arquivo(s) em $target\n";
}

$arguments = array_slice($argv, 1);
$extensions = $arguments === [] ? ['bcg729', 'swoole', 'opus', 'psampler'] : explode(',', implode(',', $arguments));
$extensions = array_values(array_unique(array_filter(array_map('trim', $extensions), 'strlen')));
$failed = false;
foreach ($extensions as $extension) {
    try {
        generateExtensionStubs($extension, __DIR__ . '/stubs');
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        $failed = true;
    }
}
exit($failed ? 1 : 0);