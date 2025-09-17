<?php

namespace Tests\Feature;

use App\Models\ArchivoReunion;
use App\Models\GoogleToken;
use App\Models\TaskLaravel;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class TaskAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_creates_documents_folder_when_missing(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $user = User::factory()->create();
        GoogleToken::create([
            'username' => $user->username,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'recordings_folder_id' => 'root-folder',
        ]);
        $task = TaskLaravel::factory()->create([
            'username' => $user->username,
        ]);

        $this->actingAs($user);

        $file = UploadedFile::fake()->create('demo.pdf', 100, 'application/pdf');

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $clientMock = Mockery::mock();

        $driveMock->shouldReceive('setAccessToken')->once()->with('access-token');
        $driveMock->shouldReceive('getClient')->andReturn($clientMock);
        $clientMock->shouldReceive('isAccessTokenExpired')->andReturn(false);
        $driveMock->shouldReceive('listFolders')
            ->once()
            ->with(Mockery::on(fn ($query) => str_contains($query, "Documentos") && str_contains($query, "root-folder")))
            ->andReturn([]);
        $driveMock->shouldReceive('createFolder')->once()->with('Documentos', 'root-folder')->andReturn('documents-folder');
        $driveMock->shouldReceive('uploadFile')
            ->once()
            ->with('demo.pdf', 'application/pdf', 'documents-folder', Mockery::type('string'))
            ->andReturn('file-123');
        $driveMock->shouldReceive('getFileLink')->once()->with('file-123')->andReturn('https://drive.test/file-123');

        $this->instance(GoogleDriveService::class, $driveMock);

        $response = $this->post("/api/tasks-laravel/tasks/{$task->id}/files", [
            'file' => $file,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('archivos_reuniones', [
            'task_id' => $task->id,
            'drive_folder_id' => 'documents-folder',
            'drive_file_id' => 'file-123',
        ]);

        $record = ArchivoReunion::first();
        $this->assertSame('https://drive.test/file-123', $record->drive_web_link);
    }

    public function test_store_uses_existing_documents_folder(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $user = User::factory()->create();
        GoogleToken::create([
            'username' => $user->username,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'recordings_folder_id' => 'root-folder',
        ]);
        $task = TaskLaravel::factory()->create([
            'username' => $user->username,
        ]);

        $this->actingAs($user);

        $file = UploadedFile::fake()->create('notas.txt', 10, 'text/plain');

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $clientMock = Mockery::mock();

        $driveMock->shouldReceive('setAccessToken')->once()->with('access-token');
        $driveMock->shouldReceive('getClient')->andReturn($clientMock);
        $clientMock->shouldReceive('isAccessTokenExpired')->andReturn(false);

        $existingFolder = new class {
            public function getId(): string
            {
                return 'existing-folder';
            }

            public function getName(): string
            {
                return 'Documentos';
            }
        };

        $driveMock->shouldReceive('listFolders')
            ->once()
            ->with(Mockery::on(fn ($query) => str_contains($query, "Documentos") && str_contains($query, "root-folder")))
            ->andReturn([$existingFolder]);
        $driveMock->shouldReceive('createFolder')->never();
        $driveMock->shouldReceive('uploadFile')
            ->once()
            ->with('notas.txt', 'text/plain', 'existing-folder', Mockery::type('string'))
            ->andReturn('file-456');
        $driveMock->shouldReceive('getFileLink')->once()->with('file-456')->andReturn('https://drive.test/file-456');

        $this->instance(GoogleDriveService::class, $driveMock);

        $response = $this->post("/api/tasks-laravel/tasks/{$task->id}/files", [
            'file' => $file,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('archivos_reuniones', [
            'task_id' => $task->id,
            'drive_folder_id' => 'existing-folder',
            'drive_file_id' => 'file-456',
        ]);
    }
}
