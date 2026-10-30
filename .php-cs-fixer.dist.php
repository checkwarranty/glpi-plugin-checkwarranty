<?php
return (new PhpCsFixer\Config())
    ->setRules(['@PSR12' => true, 'single_quote' => true, 'no_unused_imports' => true])
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/inc', __DIR__ . '/front'])->append([__DIR__ . '/setup.php', __DIR__ . '/hook.php']));
