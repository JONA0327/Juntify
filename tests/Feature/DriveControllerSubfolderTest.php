<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Services\GoogleServiceAccount;
use Illuminate\Support\Facades\Config;
use Mockery;

it('creates subfolder', function () {
    Config::set('drive.root_folder_id', 'root123');

    $user = User::factory()->create(['username' => 'testuser']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
        'recordings_folder_id' => 'main123',
    ]);

    $folder = Folder::create([
        'google_token_id' => $token->id,
        'google_id'       => 'main123',
        'name'            => 'MainFolder',
        'parent_id'       => null,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('impersonate')->once()->with($user->email);
    $service->shouldReceive('createFolder')
        ->once()->with('SubFolder', 'main123')->andReturn('sub123');

    app()->instance(GoogleServiceAccount::class, $service);

    $response = $this->actingAs($user)->post('/drive/subfolder', [
        'name' => 'SubFolder',
    ]);

    $response->assertOk()->assertJsonStructure(['id']);

    $this->assertDatabaseHas('subfolders', [
        'folder_id' => $folder->id,
        'google_id' => 'sub123',
        'name'      => 'SubFolder',
    ]);
});
