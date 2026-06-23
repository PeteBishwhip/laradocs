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
        'sections' => 'Secciones',
        'overview' => 'Resumen',
        'breadcrumb' => 'Migaja de pan',
        'home' => 'Inicio',
        'header' => 'Cabecera',
        'footer' => 'Pie',
        'pagination' => 'Paginación',
        'documentation' => 'Documentación',
        'toggle_navigation' => 'Cambiar navegación',
        'previous' => 'Anterior',
        'next' => 'Siguiente',
    ],

    'search' => [
        'label' => 'Búsqueda rápida',
        'placeholder' => 'Buscar páginas...',
        'open' => 'Abrir paleta de comandos',
        'trigger' => 'Buscar la documentación...',
        'navigate' => 'navegar por',
        'select' => 'abierto',
        'close' => 'cerrar',
    ],

    'theme' => [
        'toggle' => 'Cambiar tema',
    ],

    'toc' => [
        'label' => 'En esta página',
    ],

    'language' => [
        'label' => 'Idioma',
        'select' => 'Seleccionar idioma',
    ],

    'version' => [
        'label' => 'Versión',
        'select' => 'Seleccionar versión',
        'badge' => [
            'latest' => 'más reciente',
            'deprecated' => 'obsoleta',
            'pre_release' => 'versión preliminar',
        ],
        'outdated' => [
            'viewing' => 'Estás viendo :version.',
            'link' => 'Ver la versión actual.',
            'dismiss' => 'Cerrar',
        ],
    ],

    'page' => [
        'last_updated' => 'Última actualización :date',
        'edit' => 'Editar esta página',
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
        'read_more' => 'Leer más',
    ],

    'tags' => [
        'eyebrow' => 'Etiquetas',
        'label' => 'Etiquetas',
        'index_title' => 'Etiquetas',
        'index_intro' => 'Explora la documentación por tema.',
        'show_title' => 'Páginas etiquetadas con «:tag»',
        'count' => '{0} Ninguna página|{1} :count página|[2,*] :count páginas',
        'empty' => 'Aún no hay etiquetas.',
        'all' => 'Todas las etiquetas',
    ],

    'empty' => [
        'eyebrow' => 'Empezar',
        'title' => 'Tu documentación, lista cuando estés.',
        'intro' => 'Laradocs está conectado y esperando contenido. Se están recuperando páginas desde :path.',
        'step_one_title' => 'Acabar una página de inicio',
        'step_one_body' => 'Ejecute :command para colocar una página de bienvenida y una carpeta en su directorio de documentos.',
        'step_two_title' => 'Escribe tu primera página',
        'step_two_body' => 'Utilice :command para generar un nuevo archivo markdown con front-matter.',
        'step_three_title' => 'Ajustar el aspecto',
        'step_three_body' => 'Cambia los ajustes preestablecidos con :preset o ajusta el acento con :accent.',
        'handbook' => 'Leer el manual &rarr;',
    ],

];
