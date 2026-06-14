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

    'page' => [
        'last_updated' => 'Laatst bijgewerkt :date',
        'edit' => 'Deze pagina bewerken',
    ],

    'macros' => [
        'read_more' => 'Meer informatie',
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
