<?php

return [
    'use_embeddings' => env('AI_ASSISTANT_USE_EMBEDDINGS', true),

    'context' => [
        'container' => [
            // Límite total de fragmentos que se entregan al modelo (protege tokens)
            'max_total_fragments' => env('AI_CONTAINER_MAX_FRAGMENTS', 300),

            // Límite por reunión cuando se generan fragmentos desde .ju
            'per_meeting_limit' => env('AI_CONTAINER_PER_MEETING_LIMIT', 10),

            // Incluir por defecto el resumen agregado de todas las reuniones
            'always_aggregate_all_meetings' => env('AI_CONTAINER_ALWAYS_AGGREGATE', true),

            // Distribuir los fragmentos de forma equitativa entre reuniones (round-robin)
            // para evitar que sólo entren las primeras reuniones
            'distribute_evenly_across_meetings' => env('AI_CONTAINER_DISTRIBUTE_EVENLY', true),
        ],
    ],
];
