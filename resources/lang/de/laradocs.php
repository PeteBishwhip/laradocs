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
        'navigate' => 'navigate',
        'select' => 'apri',
        'close' => 'chiudi',
    ],

    'theme' => [
        'toggle' => 'Attiva/Disattiva tema',
    ],

    'toc' => [
        'label' => 'Su questa pagina',
    ],

    'version' => [
        'label' => 'Version',
        'select' => 'Select version',
    ],

    'language' => [
        'label' => 'Lingua',
        'select' => 'Seleziona lingua',
    ],

    'page' => [
        'last_updated' => 'Ultimo aggiornamento :date',
        'edit' => 'Modifica questa pagina',
    ],

    'macros' => [
        'read_more' => 'Leggi di più',
    ],

    'tags' => [
        'eyebrow' => 'Tag',
        'label' => 'Tag',
        'index_title' => 'Tag',
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
