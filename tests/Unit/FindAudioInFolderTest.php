<?php

use App\Services\GoogleDriveService;

class FakeDriveFile
{
    private string $id;
    private string $name;
    private string $link;

    public function __construct(string $id, string $name, string $link)
    {
        $this->id = $id;
        $this->name = $name;
        $this->link = $link;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWebContentLink(): string
    {
        return $this->link;
    }
}

class FakeFileList
{
    private array $files;

    public function __construct(array $files)
    {
        $this->files = $files;
    }

    public function getFiles(): array
    {
        return $this->files;
    }
}

class FakeFilesService
{
    private array $files;

    public function __construct(array $files)
    {
        $this->files = $files;
    }

    public function listFiles(array $params): FakeFileList
    {
        return new FakeFileList($this->files);
    }
}

class FakeDriveService
{
    public FakeFilesService $files;

    public function __construct(array $files)
    {
        $this->files = new FakeFilesService($files);
    }
}

class TestableGoogleDriveService extends GoogleDriveService
{
    public function __construct() {}

    public function setDrive($drive): void
    {
        $this->drive = $drive;
    }
}

it('detects audio file by meeting id', function () {
    $file = new FakeDriveFile('file123', '123_Reunion.aac', 'https://drive.google.com/file/d/file123/view');
    $drive = new FakeDriveService([$file]);
    $service = new TestableGoogleDriveService();
    $service->setDrive($drive);

    $result = $service->findAudioInFolder('folder1', 'Reunión', '123');

    expect($result)->toMatchArray([
        'fileId' => 'file123',
        'downloadUrl' => 'https://drive.google.com/uc?export=download&id=file123',
    ]);
});

