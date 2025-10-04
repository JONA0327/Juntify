<?php

return [
    // Lista de idiomas admitidos por la app (UI/validación)
    'supported_languages' => [
        'es', // Español
        'en', // Inglés
        'fr', // Francés
        'de', // Alemán
    ],

    // Soporte por idioma para features extra de AssemblyAI.
    // Ajusta esta matriz según la documentación vigente del proveedor.
    'extras_supported_by_language' => [
        'auto_chapters' => ['en', 'es', 'fr', 'de'],
        'summarization' => ['en', 'es', 'fr', 'de'],
        'sentiment_analysis' => ['en'],
        'entity_detection' => ['en'],
        'auto_highlights' => ['en'],
        'content_safety' => ['en'],
        'iab_categories' => ['en'],
    ],
];
