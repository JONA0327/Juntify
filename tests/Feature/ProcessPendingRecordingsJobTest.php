<?php

use App\Jobs\ProcessPendingRecordingsJob;
use App\Models\PendingRecording;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('processes pending recordings and updates status', function () {
    $user = User::factory()->create();

    $recording = PendingRecording::create([
        'username' => $user->username,
        'meeting_name' => 'Test',
        'audio_drive_id' => 'drive123',
    ]);

    ProcessPendingRecordingsJob::dispatchSync();

    $recording->refresh();

    expect($recording->status)->toBe(PendingRecording::STATUS_COMPLETED);

    $notification = Notification::first();
    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe('audio_upload')
        ->and($notification->status)->toBe('completed');
});

it('completes recording when duration is exactly two hours', function () {
    $user = User::factory()->create();

    $recording = PendingRecording::create([
        'username' => $user->username,
        'meeting_name' => 'Test',
        'audio_drive_id' => 'drive123',
        'duration' => 7200,
    ]);

    ProcessPendingRecordingsJob::dispatchSync();

    $recording->refresh();

    expect($recording->status)->toBe(PendingRecording::STATUS_COMPLETED);

    $notification = Notification::first();
    expect($notification)->not->toBeNull()
        ->and($notification->status)->toBe('completed');
});

it('fails recording when duration exceeds two hours', function () {
    $user = User::factory()->create();

    $recording = PendingRecording::create([
        'username' => $user->username,
        'meeting_name' => 'Test',
        'audio_drive_id' => 'drive123',
        'duration' => 7201,
    ]);

    ProcessPendingRecordingsJob::dispatchSync();

    $recording->refresh();

    expect($recording->status)->toBe(PendingRecording::STATUS_FAILED);

    $notification = Notification::first();
    expect($notification)->not->toBeNull()
        ->and($notification->status)->toBe('failed')
        ->and($notification->message)->toBe('Duración excedida');
});
