<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Laradocs UI Strings
    |--------------------------------------------------------------------------
    |
    | Every user-facing string rendered by the bundled documentation views
    | lives here. Publish this file with
    |
    |     php artisan vendor:publish --tag=laradocs-lang
    |
    | and copy the `en` directory to another locale (e.g. `fr`) to translate
    | the interface. See the "Localisation" guide for the full workflow.
    |
    */

    'nav' => [
        'sections' => 'Sections',
        'overview' => 'Overview',
        'breadcrumb' => 'Breadcrumb',
        'home' => 'Home',
        'header' => 'Header',
        'footer' => 'Footer',
        'pagination' => 'Pagination',
        'documentation' => 'Documentation',
        'toggle_navigation' => 'Toggle navigation',
        'previous' => 'Previous',
        'next' => 'Next',
    ],

    'search' => [
        'label' => 'Quick search',
        'placeholder' => 'Search pages...',
        'open' => 'Open command palette',
        'trigger' => 'Search the docs...',
        'navigate' => 'navigate',
        'select' => 'open',
        'close' => 'close',
    ],

    'theme' => [
        'toggle' => 'Toggle theme',
    ],

    'toc' => [
        'label' => 'On this page',
    ],

    'language' => [
        'label' => 'Language',
        'select' => 'Select language',
    ],

    'version' => [
        'label' => 'Version',
        'select' => 'Select version',
    ],

    'page' => [
        'last_updated' => 'Last updated :date',
        'edit' => 'Edit this page',
    ],

    'macros' => [
        'read_more' => 'Read more',
    ],

    'tags' => [
        'eyebrow' => 'Tags',
        'label' => 'Tags',
        'index_title' => 'Tags',
        'index_intro' => 'Browse the documentation by topic.',
        'show_title' => 'Pages tagged “:tag”',
        'count' => '{0} No pages|{1} :count page|[2,*] :count pages',
        'empty' => 'No tags yet.',
        'all' => 'All tags',
    ],

    'empty' => [
        'eyebrow' => 'Get started',
        'title' => 'Your documentation, ready when you are.',
        'intro' => 'Laradocs is wired up and waiting for content. Pages are sourced from :path.',
        'step_one_title' => 'Scaffold a starter page',
        'step_one_body' => 'Run :command to drop a welcome page and folder into your docs directory.',
        'step_two_title' => 'Write your first page',
        'step_two_body' => 'Use :command to generate a new markdown file with front-matter.',
        'step_three_title' => 'Tune the look',
        'step_three_body' => 'Switch presets with :preset or tune the accent with :accent.',
        'handbook' => 'Read the handbook &rarr;',
    ],

];
