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
        'sections' => 'Sezioni',
        'overview' => 'Panoramica',
        'breadcrumb' => 'Pangrattato',
        'home' => 'Casa',
        'header' => 'Intestazione',
        'footer' => 'Piè di pagina',
        'pagination' => 'Paginazione',
        'documentation' => 'Documentazione',
        'toggle_navigation' => 'Attiva/disattiva navigazione',
        'previous' => 'Precedente',
        'next' => 'Successivo',
    ],

    'search' => [
        'label' => 'Ricerca rapida',
        'placeholder' => 'Cerca pagine...',
        'open' => 'Apri tavolozza comandi',
        'trigger' => 'Cerca nei documenti...',
        'navigate' => 'navigare',
        'select' => 'apri',
        'close' => 'chiudi',
    ],

    'theme' => [
        'toggle' => 'Attiva/Disattiva tema',
    ],

    'toc' => [
        'label' => 'Su questa pagina',
    ],

    'language' => [
        'label' => 'Lingua',
        'select' => 'Seleziona lingua',
    ],

    'version' => [
        'label' => 'Versione',
        'select' => 'Seleziona la versione',
        'badge' => [
            'latest' => 'più recente',
            'deprecated' => 'obsoleta',
            'pre_release' => 'anteprima',
        ],
        'outdated' => [
            'viewing' => 'Stai visualizzando :version.',
            'link' => 'Vai alla versione attuale.',
            'dismiss' => 'Chiudi',
        ],
    ],

    'page' => [
        'last_updated' => 'Ultimo aggiornamento :date',
        'edit' => 'Modifica questa pagina',
    ],

    'callouts' => [
        'note' => 'Nota',
        'tip' => 'Tip',
        'important' => 'Importante',
        'warning' => 'Attenzione',
        'danger' => 'Pericolo',
        'caution' => 'Attenzione',
    ],

    'macros' => [
        'read_more' => 'Leggi di più',
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
        'version' => 'Versione',
        'resources' => 'Resources',
        'request' => 'Request',
        'response' => 'Response',
        'language' => 'Lingua',
        'code_samples' => 'Request and response samples',
        'expand_all' => 'Expand all',
        'collapse_all' => 'Collapse all',
        'in' => [
            'path' => 'Path',
            'query' => 'Query',
            'header' => 'Intestazione',
            'cookie' => 'Cookie',
        ],
        // {1} = singular, [2,*] = plural — e.g. "1 endpoint" / "6 endpoints".
        'endpoint_count' => '{1} :count endpoint|[2,*] :count endpoints',
    ],

    'tags' => [
        'eyebrow' => 'Etichetta',
        'label' => 'Etichetta',
        'index_title' => 'Etichetta',
        'index_intro' => 'Esplora la documentazione per argomento.',
        'show_title' => 'Pagine con il tag «:tag»',
        'count' => '{0} Nessuna pagina|{1} :count pagina|[2,*] :count pagine',
        'empty' => 'Ancora nessun tag.',
        'all' => 'Tutti i tag',
    ],

    'empty' => [
        'eyebrow' => 'Per iniziare',
        'title' => 'La tua documentazione, pronta quando ti trovi.',
        'intro' => 'Laradocs è cablato e in attesa di contenuti. Le pagine provengono da :path.',
        'step_one_title' => 'Struttura una pagina iniziale',
        'step_one_body' => 'Eseguire :command per rilasciare una pagina di benvenuto e una cartella nella cartella docs.',
        'step_two_title' => 'Scrivi la tua prima pagina',
        'step_two_body' => 'Usa :command per generare un nuovo file markdown con front-matter.',
        'step_three_title' => 'Sintonizza l\'aspetto',
        'step_three_body' => 'Cambia preset con :preset o sintonizza l\'accento con :accent.',
        'handbook' => 'Leggi il manuale &rarr;',
    ],

];
