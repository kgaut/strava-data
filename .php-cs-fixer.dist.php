<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->exclude('var');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP83Migration' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => false,
        'phpdoc_to_comment' => false,
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache');
