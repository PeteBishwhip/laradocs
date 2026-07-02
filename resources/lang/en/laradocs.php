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
        'badge' => [
            'latest' => 'latest',
            'deprecated' => 'deprecated',
            'pre_release' => 'pre-release',
        ],
        'outdated' => [
            'viewing' => 'You are viewing :version.',
            'link' => 'View the current version.',
            'dismiss' => 'Dismiss',
        ],
    ],

    'page' => [
        'last_updated' => 'Last updated :date',
        'edit' => 'Edit this page',
    ],

    'callouts' => [
        'note' => 'Note',
        'tip' => 'Tip',
        'important' => 'Important',
        'warning' => 'Warning',
        'danger' => 'Danger',
        'caution' => 'Caution',
    ],

    'macros' => [
        'read_more' => 'Read more',
    ],

    'openapi' => [
        'deprecated' => 'Deprecated',
        'parameters' => 'Parameters',
        'request_body' => 'Request Body',
        'responses' => 'Responses',
        'required' => 'Required',
        'optional' => 'Optional',
        'nullable' => 'nullable',
        'enum' => 'Allowed values',
        'items' => 'Items',
        'servers' => 'Servers',
        'operations' => 'Operations',
        'one_of' => 'One of',
        'any_of' => 'Any of',
        'circular' => 'Circular reference',
        'unresolved' => 'Unresolved reference',
        'default_tag' => 'Other',

        // Redesigned reference UI.
        'copy' => 'Copy',
        'copy_endpoint' => 'Copy endpoint',
        'base_url' => 'Base URL',
        'version' => 'Version',
        'resources' => 'Resources',
        'request' => 'Request',
        'response' => 'Response',
        'code_samples' => 'Request and response samples',
        'expand_all' => 'Expand all',
        'collapse_all' => 'Collapse all',
        'in' => [
            'path' => 'Path',
            'query' => 'Query',
            'header' => 'Header',
            'cookie' => 'Cookie',
        ],
        // {1} = singular, [2,*] = plural — e.g. "1 endpoint" / "6 endpoints".
        'endpoint_count' => '{1} :count endpoint|[2,*] :count endpoints',
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
