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
        'select' => 'Version auswählen',
        'badge' => [
            'latest' => 'aktuell',
            'deprecated' => 'veraltet',
            'pre_release' => 'Vorabversion',
        ],
        'outdated' => [
            'viewing' => 'Sie sehen :version.',
            'link' => 'Zur aktuellen Version.',
            'dismiss' => 'Schließen',
        ],
    ],

    'page' => [
        'last_updated' => 'Zuletzt aktualisiert :date',
        'edit' => 'Diese Seite bearbeiten',
    ],

    'callouts' => [
        'note' => 'Notiz',
        'tip' => 'Tipp',
        'important' => 'Wichtig',
        'warning' => 'Warnung',
        'danger' => 'Gefahr',
        'caution' => 'Vorsicht',
    ],

    'macros' => [
        'read_more' => 'Lesen Sie mehr',
    ],

    'openapi' => [
        'deprecated' => 'Veraltet',
        'parameters' => 'Parameter',
        'request_body' => 'Anfrage',
        'responses' => 'Antworten',
        'required' => 'Erforderlich',
        'optional' => 'Optional',
        'nullable' => 'Nullierbar',
        'enum' => 'Erlaubte Werte',
        'items' => 'Artikel',
        'servers' => 'Server',
        'operations' => 'Betrieb',
        'one_of' => 'Einer von',
        'any_of' => 'Irgendeine von',
        'circular' => 'Zirkuläre Referenz',
        'unresolved' => 'Ungelöste Referenz',
        'default_tag' => 'Sonstiges',

        // Redesigned reference UI.
        'copy' => 'Verstanden',
        'copy_endpoint' => 'Endpunkt kopieren',
        'base_url' => 'Basis-URL',
        'version' => 'Version',
        'resources' => 'Ressourcen',
        'request' => 'Anfrage',
        'response' => 'Reaktion',
        'language' => 'Sprache',
        'code_samples' => 'Anfrage- und Antwortbeispiele',
        'expand_all' => 'Alle erweitern',
        'collapse_all' => 'Alle einstürzen',
        'in' => [
            'path' => 'Verlauf',
            'query' => 'Abfrage',
            'header' => 'Kopfzeile',
            'cookie' => 'Keks',
        ],
        // {1} = singular, [2,*] = plural — e.g. "1 endpoint" / "6 endpoints".
        'endpoint_count' => '{1} :count Endpunkt|[2,*] :count Endpunkte',
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
