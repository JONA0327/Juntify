<?php

use App\Models\Group;
use App\Models\MeetingContentContainer;
use App\Models\Organization;
use App\Models\OrganizationGoogleToken;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\OrganizationDriveHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;

uses(RefreshDatabase::class);

uses()->afterEach(function () {
    Mockery::close();
});

test('user can list Drive documents for an accessible container', function () {
    $user = User::factory()->create();

    $organization = Organization::create([
        'nombre_organizacion' => 'Acme Inc.',
        'descripcion' => 'Org test',
        'admin_id' => $user->id,
    ]);

    OrganizationGoogleToken::create([
        'organization_id' => $organization->id,
        'access_token' => ['access_token' => 'token-abc'],
        'refresh_token' => 'refresh-123',
        'expiry_date' => now()->addHour(),
    ]);

    $organizationToken = OrganizationGoogleToken::first();
    expect($organizationToken->access_token)->toBe(['access_token' => 'token-abc']);
    expect($organizationToken->refresh_token)->toBe('refresh-123');

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Equipo Legal',
        'descripcion' => 'Grupo de pruebas',
    ]);

    DB::table('group_user')->insert([
        'id_grupo' => $group->id,
        'user_id' => $user->id,
        'rol' => 'colaborador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $container = MeetingContentContainer::create([
        'name' => 'Dossier Comercial',
        'description' => 'Carpeta compartida',
        'username' => 'owner-user',
        'group_id' => $group->id,
        'is_active' => true,
    ]);

    $driveFiles = [
        new class {
            public function getId() { return 'drive-file-1'; }
            public function getName() { return 'Contrato.pdf'; }
            public function getMimeType() { return 'application/pdf'; }
            public function getSize() { return 1024; }
            public function getModifiedTime() { return '2024-05-01T10:00:00Z'; }
            public function getWebViewLink() { return 'https://drive.test/file/1'; }
        },
    ];

    $drive = new class($driveFiles) {
        private $filesResource;
        public function __construct(array $files)
        {
            $this->filesResource = new class($files) {
                private array $files;
                public function __construct(array $files)
                {
                    $this->files = $files;
                }
                public function listFiles(array $params)
                {
                    return new class($this->files) {
                        private array $files;
                        public function __construct(array $files)
                        {
                            $this->files = $files;
                        }
                        public function getFiles(): array
                        {
                            return $this->files;
                        }
                    };
                }
            };
        }
        public function __get($name)
        {
            if ($name === 'files') {
                return $this->filesResource;
            }
        }
    };

    $client = new class {
        public array $token = [];
        public function setAccessToken($token): void
        {
            $this->token = is_array($token) ? $token : [];
        }
        public function isAccessTokenExpired(): bool
        {
            return false;
        }
    };

    $driveService = Mockery::mock(GoogleDriveService::class);
    $driveService->shouldReceive('setAccessToken')->andReturnNull();
    $driveService->shouldReceive('getClient')->andReturn($client);
    $driveService->shouldReceive('getDrive')->andReturn($drive);

    app()->instance(GoogleDriveService::class, $driveService);

    $helper = Mockery::mock(OrganizationDriveHelper::class);
    $helper->shouldReceive('ensureContainerFolder')
        ->once()
        ->with(Mockery::type(Group::class), Mockery::type(MeetingContentContainer::class))
        ->andReturn([
            'id' => 'folder-xyz',
            'metadata' => ['name' => 'Dossier Comercial'],
        ]);

    app()->instance(OrganizationDriveHelper::class, $helper);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.ai-assistant.documents.drive', ['container_id' => $container->id]));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('folder_id', 'folder-xyz')
        ->assertJsonPath('folder.scope', 'container')
        ->assertJsonPath('folder.drive_type', 'organization')
        ->assertJsonPath('folder.container_id', $container->id)
        ->assertJsonPath('files.0.id', 'drive-file-1')
        ->assertJsonPath('files.0.name', 'Contrato.pdf');

    expect(MeetingContentContainer::find($container->id)->drive_folder_id)->toBe('folder-xyz');
});

test('user without container access receives forbidden response', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $organization = Organization::create([
        'nombre_organizacion' => 'Globex',
        'descripcion' => 'Org demo',
        'admin_id' => $owner->id,
    ]);

    OrganizationGoogleToken::create([
        'organization_id' => $organization->id,
        'access_token' => ['access_token' => 'token-xyz'],
        'refresh_token' => 'refresh-xyz',
        'expiry_date' => now()->addHour(),
    ]);

    $orgToken = OrganizationGoogleToken::first();
    expect($orgToken->access_token)->toBe(['access_token' => 'token-xyz']);
    expect($orgToken->refresh_token)->toBe('refresh-xyz');

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Ventas',
        'descripcion' => 'Grupo Ventas',
    ]);

    DB::table('group_user')->insert([
        'id_grupo' => $group->id,
        'user_id' => $owner->id,
        'rol' => 'administrador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $container = MeetingContentContainer::create([
        'name' => 'Pipeline',
        'username' => $owner->username,
        'group_id' => $group->id,
        'is_active' => true,
        'drive_folder_id' => 'existing-folder',
    ]);

    $driveService = Mockery::mock(GoogleDriveService::class)->shouldIgnoreMissing();
    app()->instance(GoogleDriveService::class, $driveService);

    $helper = Mockery::mock(OrganizationDriveHelper::class);
    $helper->shouldReceive('ensureContainerFolder')->never();
    app()->instance(OrganizationDriveHelper::class, $helper);

    $response = $this->actingAs($intruder, 'sanctum')
        ->getJson(route('api.ai-assistant.documents.drive', ['container_id' => $container->id]));

    $response->assertStatus(403)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['message']);
});
