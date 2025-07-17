<?php
// config/drive.php

return [
    // ruta relativa desde .env → storage_path(...)
    'credentials_path' => storage_path(env('GOOGLE_APPLICATION_CREDENTIALS')),
    'root_folder_id'   => env('GOOGLE_DRIVE_ROOT_FOLDER', ''),
];
