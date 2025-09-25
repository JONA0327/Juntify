<?php

use App\Models\Folder;
use App\Models\Subfolder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;

it('encrypts plaintext google_ids into enc/hash columns', function () {
    $folder = Folder::create([
        'google_token_id' => 1,
        'google_id' => 'plain-folder-id',
        'name' => 'Root',
    ]);

    $sub = Subfolder::create([
        'folder_id' => $folder->id,
        'google_id' => 'plain-sub-id',
        'name' => 'Transcripciones',
    ]);

    $exitCode = Artisan::call('encrypt:drive-ids');
    expect($exitCode)->toBe(0);

    $folder->refresh();
    expect($folder->getRawOriginal('google_id'))->toBeNull();
    expect($folder->google_id)->toBe('plain-folder-id');
    expect($folder->google_id_hash)->toBe(hash('sha256', 'plain-folder-id'));
    expect(Crypt::decryptString($folder->getRawOriginal('google_id_enc')))->toBe('plain-folder-id');

    $sub->refresh();
    expect($sub->getRawOriginal('google_id'))->toBeNull();
    expect($sub->google_id)->toBe('plain-sub-id');
    expect($sub->google_id_hash)->toBe(hash('sha256', 'plain-sub-id'));
    expect(Crypt::decryptString($sub->getRawOriginal('google_id_enc')))->toBe('plain-sub-id');
});
