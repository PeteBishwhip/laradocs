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
        'sections' => 'Secties',
        'overview' => 'Overzicht',
        'breadcrumb' => 'Kruimelpad',
        'home' => 'Startpagina',
        'header' => 'Koptekst',
        'footer' => 'Voettekst',
        'pagination' => 'Paginering',
        'documentation' => 'Documentatie',
        'toggle_navigation' => 'Navigatie in-/uitschakelen',
        'previous' => 'Vorig',
        'next' => 'Volgende',
    ],

    'search' => [
        'label' => 'Snel zoeken',
        'placeholder' => 'Zoek pagina\'s...',
        'open' => 'Open opdracht palet',
        'trigger' => 'Doorzoek de documenten...',
        'navigate' => 'navigeren',
        'select' => 'open',
        'close' => 'sluiten',
    ],

    'theme' => [
        'toggle' => 'Thema aan/uit',
    ],

    'toc' => [
        'label' => 'Op deze pagina',
    ],

    'language' => [
        'label' => 'Taal',
        'select' => 'Taal selecteren',
    ],

    'version' => [
        'label' => 'Versie',
        'select' => 'Selecteer versie',
        'badge' => [
            'latest' => 'nieuwste',
            'deprecated' => 'verouderd',
            'pre_release' => 'voorlopig',
        ],
        'outdated' => [
            'viewing' => 'Je bekijkt :version.',
            'link' => 'Bekijk de huidige versie.',
            'dismiss' => 'Sluiten',
        ],
    ],

    'page' => [
        'last_updated' => 'Laatst bijgewerkt :date',
        'edit' => 'Deze pagina bewerken',
    ],

    'callouts' => [
        'note' => 'Notitie',
        'tip' => 'Tip',
        'important' => 'Belangrijke',
        'warning' => 'Waarschuwing',
        'danger' => 'Gevaarlijk',
        'caution' => 'Voorzichtigheid',
    ],

    'macros' => [
        'read_more' => 'Meer informatie',
    ],

    'tags' => [
        'eyebrow' => 'Tags',
        'label' => 'Tags',
        'index_title' => 'Tags',
        'index_intro' => 'Blader door de documentatie op onderwerp.',
        'show_title' => 'Pagina’s met de tag “:tag”',
        'count' => '{0} Geen pagina’s|{1} :count pagina|[2,*] :count pagina’s',
        'empty' => 'Nog geen tags.',
        'all' => 'Alle tags',
    ],

    'empty' => [
        'eyebrow' => 'Aan de slag',
        'title' => 'Uw documentatie, klaar wanneer u klaar bent.',
        'intro' => 'LaraDocs is bedraad en wachten op inhoud. Pagina\'s zijn afkomstig uit :path.',
        'step_one_title' => 'Steiger een startpagina',
        'step_one_body' => 'Voer :command uit om een welkomstpagina en map in uw documentenmap te plaatsen.',
        'step_two_title' => 'Schrijf je eerste pagina',
        'step_two_body' => 'Gebruik :command om een nieuw markdown bestand te genereren met de voorkant.',
        'step_three_title' => 'Pas het uiterlijk af',
        'step_three_body' => 'Verander instellingen met :preset of pas het accent af met :accent.',
        'handbook' => 'Lees de handleiding &rarr;',
    ],

];
