<?php
// config/drive.php

return [
    // Parent folder (or Shared Drive ID) where personal roots are created
    'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER', ''),
    // Parent folder (or Shared Drive ID) where ORGANIZATION roots are created
    // Falls back to GOOGLE_DRIVE_ROOT_FOLDER if not set
    'org_root_folder_id' => env('GOOGLE_DRIVE_ORG_ROOT_FOLDER', env('GOOGLE_DRIVE_ROOT_FOLDER', '')),
    'default_subfolders' => [
        'Audios',
        'Transcripciones',
        'Resúmenes',
    ],
];
