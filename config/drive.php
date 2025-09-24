<?php
// config/drive.php

return [
    // Parent folder (or Shared Drive ID) where personal roots are created
    'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER', ''),
    // Parent folder (or Shared Drive ID) where ORGANIZATION roots are created
    // Falls back to GOOGLE_DRIVE_ROOT_FOLDER if not set
    'org_root_folder_id' => env('GOOGLE_DRIVE_ORG_ROOT_FOLDER', env('GOOGLE_DRIVE_ROOT_FOLDER', '')),
    // Default name for the per-user root recordings folder
    // Can be overridden via env GOOGLE_DRIVE_DEFAULT_ROOT_NAME
    'default_root_folder_name' => env('GOOGLE_DRIVE_DEFAULT_ROOT_NAME', 'Juntify Recordings'),
    'default_subfolders' => [
        // Keep order stable; these will be ensured (created if missing)
        'Audios',                // Raw audio uploads
        'Transcripciones',       // Transcript text files
        'Audios Pospuestos',     // Deferred / pending processing audios
        'Documentos',            // Generated / user documents
    ],
];
