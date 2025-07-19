<?php
// config/drive.php

return [
    'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER', ''),
    'default_subfolders' => [
        'Audios',
        'Transcripciones',
        'Resúmenes',
    ],
];
