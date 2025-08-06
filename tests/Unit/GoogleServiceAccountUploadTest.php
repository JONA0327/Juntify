<?php

use App\Services\GoogleServiceAccount;
use Google\Service\Drive;

class FakeFiles
{
    public array $lastParams = [];

    public function create($fileMetadata, array $params)
    {
        $this->lastParams = $params;
        return (object) ['id' => 'fake'];
    }
}

class FakeDrive extends Drive
{
    public FakeFiles $files;

    public function __construct()
    {
        $this->files = new FakeFiles();
    }
}

class TestableGoogleServiceAccount extends GoogleServiceAccount
{
    public function __construct() {}

    public function setDrive(Drive $drive): void
    {
        $this->drive = $drive;
    }
}

it('uploads resource using media upload and closes resource', function () {
    $contents = str_repeat('A', 5 * 1024 * 1024);
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $contents);
    rewind($handle);

    $drive = new FakeDrive();
    $service = new TestableGoogleServiceAccount();
    $service->setDrive($drive);

    $id = $service->uploadFile('test.txt', 'text/plain', 'parent', $handle);

    expect($id)->toBe('fake');
    expect($drive->files->lastParams['uploadType'])->toBe('media');
    expect($drive->files->lastParams['data'])->toBe($contents);
    expect(is_resource($handle))->toBeFalse();
});

it('uploads file path using media upload', function () {
    $contents = str_repeat('B', 5 * 1024 * 1024);
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $contents);

    $drive = new FakeDrive();
    $service = new TestableGoogleServiceAccount();
    $service->setDrive($drive);

    $id = $service->uploadFile('test.txt', 'text/plain', 'parent', $path);

    expect($id)->toBe('fake');
    expect($drive->files->lastParams['uploadType'])->toBe('media');
    expect($drive->files->lastParams['data'])->toBe($contents);

    unlink($path);
});
