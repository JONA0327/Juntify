<?php

use App\Http\Controllers\MeetingController;
use App\Services\GoogleDriveService;
it('converts aac extension to m4a', function () {
    $service = \Mockery::mock(GoogleDriveService::class);
    $controller = new MeetingController($service);

    $reflection = new ReflectionClass(MeetingController::class);
    $method = $reflection->getMethod('detectAudioExtension');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'audio.aac', 'audio/aac');

    expect($result)->toBe('m4a');
});
