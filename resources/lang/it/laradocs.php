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
        'breadcrumb' => 'Briciol di pane',
        'home' => 'Casa',
        'header' => 'Intestazione',
        'footer' => 'Piè di pagina',
        'pagination' => 'Paginazione',
        'documentation' => 'Documentazione',
        'toggle_navigation' => 'Commuta navigazione',
        'previous' => 'Precedente',
        'next' => 'Prossimo',
    ],

    'search' => [
        'label' => 'Ricerca rapida',
        'placeholder' => 'Pagine di ricerca...',
        'open' => 'Palette di comandi aperta',
        'trigger' => 'Cerca nei documenti...',
        'navigate' => 'naviga',
        'select' => 'apri',
        'close' => 'chiude',
    ],

    'theme' => [
        'toggle' => 'Toggle tema',
    ],

    'toc' => [
        'label' => 'In questa pagina',
    ],

    'language' => [
        'label' => 'Lingua',
        'select' => 'Linguaggio selezionato',
    ],

    'version' => [
        'label' => 'Versione',
        'select' => 'Versione selezionata',
        'badge' => [
            'latest' => 'ultimi anni',
            'deprecated' => 'deprecato',
            'pre_release' => 'pre-uscita',
        ],
        'outdated' => [
            'viewing' => 'Stai guardando :version.',
            'link' => 'Visualizza la versione attuale.',
            'dismiss' => 'Licenziamento',
        ],
    ],

    'page' => [
        'last_updated' => 'Ultimo aggiornamento :d ate',
        'edit' => 'Modifica questa pagina',
    ],

    'callouts' => [
        'note' => 'Nota',
        'tip' => 'Consiglio',
        'important' => 'Importante',
        'warning' => 'Avviso',
        'danger' => 'Pericolo',
        'caution' => 'Attenzione',
    ],

    'macros' => [
        'read_more' => 'Leggi di più',
    ],

    'openapi' => [
        'deprecated' => 'Deprecato',
        'parameters' => 'Parametri',
        'request_body' => 'Organismo Richiedente',
        'responses' => 'Risposte',
        'required' => 'Richiesto',
        'optional' => 'Opzionale',
        'nullable' => 'nullabile',
        'enum' => 'Valori consentiti',
        'items' => 'Oggetti',
        'servers' => 'Server',
        'operations' => 'Operazioni',
        'one_of' => 'Uno di',
        'any_of' => 'Qualsiasi di',
        'circular' => 'Riferimento circolare',
        'unresolved' => 'Riferimento irrisolto',
        'default_tag' => 'Altro',

        // Redesigned reference UI.
        'copy' => 'Ricevuto',
        'copy_endpoint' => 'Copia endpoint',
        'base_url' => 'Base URL',
        'version' => 'Versione',
        'resources' => 'Risorse',
        'request' => 'Richiesta',
        'response' => 'Risposta',
        'language' => 'Lingua',
        'code_samples' => 'Esempi di richieste e risposte',
        'expand_all' => 'Espandi tutto',
        'collapse_all' => 'Collasso tutto',
        'in' => [
            'path' => 'Percorso',
            'query' => 'Query',
            'header' => 'Intestazione',
            'cookie' => 'Cookie',
        ],
        // {1} = singular, [2,*] = plural — e.g. "1 endpoint" / "6 endpoints".
        'endpoint_count' => '{1} :count endpoint|[2,*] :count endpoints',
    ],

    'tags' => [
        'eyebrow' => 'Tags',
        'label' => 'Tags',
        'index_title' => 'Tags',
        'index_intro' => 'Sfoglia la documentazione per argomento.',
        'show_title' => 'Pagine taggate ":tag"',
        'count' => '{0} Nessuna pagina|{1} :count page|[2,*] :count le pagine',
        'empty' => 'Ancora nessun tag.',
        'all' => 'Tutti i tag',
    ],

    'empty' => [
        'eyebrow' => 'Inizia',
        'title' => 'La tua documentazione, pronta quando vuoi.',
        'intro' => 'Laradocs è cablata e in attesa di contenuti. Le pagine sono tratte da :p ath.',
        'step_one_title' => 'Impalcatura una pagina iniziale',
        'step_one_body' => 'Esegui :command per inserire una pagina di benvenuto e una cartella nella tua cartella docs.',
        'step_two_title' => 'Scrivi la tua prima pagina',
        'step_two_body' => 'Usa :command per generare un nuovo file markdown con front-matter.',
        'step_three_title' => 'Affina il look',
        'step_three_body' => 'Cambia preset con :p reset o accorda l\'accento con :accent.',
        'handbook' => 'Leggi il manuale &rarr;',
    ],

];
