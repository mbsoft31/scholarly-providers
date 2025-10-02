<?php

$finder = PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'single_quote' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
