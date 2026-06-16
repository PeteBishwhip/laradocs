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
        'sections' => 'Avsnitt',
        'overview' => 'Översikt',
        'breadcrumb' => 'Länkstig',
        'home' => 'Hem',
        'header' => 'Rubrik',
        'footer' => 'Sidfot',
        'pagination' => 'Sidnumrering',
        'documentation' => 'Dokumentation',
        'toggle_navigation' => 'Växla navigering',
        'previous' => 'Föregående',
        'next' => 'Nästa',
    ],

    'search' => [
        'label' => 'Snabb sökning',
        'placeholder' => 'Sök sidor...',
        'open' => 'Öppna kommandopaletten',
        'trigger' => 'Sök i dokumenten...',
        'navigate' => 'navigera',
        'select' => 'öppen',
        'close' => 'stäng',
    ],

    'theme' => [
        'toggle' => 'Växla tema',
    ],

    'toc' => [
        'label' => 'På denna sida',
    ],

    'language' => [
        'label' => 'Språk',
        'select' => 'Välj språk',
    ],

    'version' => [
        'label' => 'Version',
        'select' => 'Select version',
    ],

    'page' => [
        'last_updated' => 'Senast uppdaterad :date',
        'edit' => 'Redigera denna sida',
    ],

    'macros' => [
        'read_more' => 'Läs mer',
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
        'eyebrow' => 'Kom igång',
        'title' => 'Din dokumentation, klar när du är.',
        'intro' => 'Laradocs är uppkopplad och väntar på innehåll. Sidor hämtas från :path.',
        'step_one_title' => 'Ställning en startsida',
        'step_one_body' => 'Kör :command för att släppa en välkomstsida och mapp till din docs-katalog.',
        'step_two_title' => 'Skriv din första sida',
        'step_two_body' => 'Använd :command för att skapa en ny markdown-fil med front-matter.',
        'step_three_title' => 'Justera utseendet',
        'step_three_body' => 'Växla förval med :preset eller tonsätt accenten med :accent.',
        'handbook' => 'Läs handboken &rarr;',
    ],

];
