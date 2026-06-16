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
        'sections' => 'Abschnitte',
        'overview' => 'Übersicht',
        'breadcrumb' => 'Brotkrumen',
        'home' => 'Heim',
        'header' => 'Kopfzeile',
        'footer' => 'Fußzeile',
        'pagination' => 'Paginierung',
        'documentation' => 'Dokumentation',
        'toggle_navigation' => 'Navigation umschalten',
        'previous' => 'Vorherige',
        'next' => 'Nächste',
    ],

    'search' => [
        'label' => 'Schnellsuche',
        'placeholder' => 'Seiten suchen...',
        'open' => 'Kommandopalette öffnen',
        'trigger' => 'Durchsuche die Dokumente...',
        'navigate' => 'navigieren',
        'select' => 'öffnen',
        'close' => 'schließen',
    ],

    'theme' => [
        'toggle' => 'Theme umschalten',
    ],

    'toc' => [
        'label' => 'Auf dieser Seite',
    ],

    'language' => [
        'label' => 'Sprache',
        'select' => 'Sprache auswählen',
    ],

    'version' => [
        'label' => 'Version',
        'select' => 'Select version',
    ],

    'page' => [
        'last_updated' => 'Zuletzt aktualisiert :date',
        'edit' => 'Diese Seite bearbeiten',
    ],

    'macros' => [
        'read_more' => 'Lesen Sie mehr',
    ],

    'tags' => [
        'eyebrow' => 'Schlagwörter',
        'label' => 'Schlagwörter',
        'index_title' => 'Schlagwörter',
        'index_intro' => 'Durchsuchen Sie die Dokumentation nach Thema.',
        'show_title' => 'Seiten mit dem Schlagwort „:tag“',
        'count' => '{0} Keine Seiten|{1} :count Seite|[2,*] :count Seiten',
        'empty' => 'Noch keine Schlagwörter.',
        'all' => 'Alle Schlagwörter',
    ],

    'empty' => [
        'eyebrow' => 'Los geht\'s',
        'title' => 'Ihre Dokumentation, bereit, wenn Sie es sind.',
        'intro' => 'Laradocs ist verdrahtet und wartet auf Inhalte. Seiten werden aus :path.',
        'step_one_title' => 'Starter-Seite Gerüst',
        'step_one_body' => 'Führen Sie :command aus, um eine Willkommensseite und einen Ordner in Ihr Dokumentenverzeichnis zu legen.',
        'step_two_title' => 'Schreibe deine erste Seite',
        'step_two_body' => 'Verwenden Sie :command um eine neue Markdown Datei mit Front-matter zu erzeugen.',
        'step_three_title' => 'Lookout verlängern',
        'step_three_body' => 'Schalten Sie die Voreinstellungen mit :preset oder passen Sie den Akzent mit :accent an.',
        'handbook' => 'Lesen Sie das Handbuch &rarr;',
    ],

];
