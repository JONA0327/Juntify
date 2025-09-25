<?php

use App\Models\Group;
use App\Models\GroupDriveFolder;
use App\Models\MeetingContentContainer;
use App\Models\Organization;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\OrganizationDriveHelper;
use Google\Service\Drive\DriveFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;

uses(RefreshDatabase::class);

uses()->afterEach(function () {
    Mockery::close();
});

test('group documents endpoint returns grouped containers with nested drive data', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org Demo',
        'descripcion' => 'Demo',
        'admin_id' => $user->id,
    ]);

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Equipo Legal',
        'descripcion' => 'Grupo de prueba',
    ]);

    DB::table('group_user')->insert([
        'id_grupo' => $group->id,
        'user_id' => $user->id,
        'rol' => 'colaborador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $containerWithFolder = MeetingContentContainer::create([
        'name' => 'Carpeta Operativa',
        'description' => 'Documentos del día a día',
        'username' => $user->username,
        'group_id' => $group->id,
        'drive_folder_id' => 'container-folder-1',
        'is_active' => true,
    ]);

    $containerWithoutFolder = MeetingContentContainer::create([
        'name' => 'Onboarding',
        'description' => 'Material para nuevas incorporaciones',
        'username' => $user->username,
        'group_id' => $group->id,
        'drive_folder_id' => null,
        'is_active' => true,
    ]);

    $inactiveContainer = MeetingContentContainer::create([
        'name' => 'Archivado',
        'username' => $user->username,
        'group_id' => $group->id,
        'drive_folder_id' => 'container-folder-inactive',
        'is_active' => false,
    ]);

    $groupFile = new DriveFile([
        'id' => 'group-file-1',
        'name' => 'Plan de trabajo.pdf',
        'mimeType' => 'application/pdf',
        'iconLink' => 'https://example.com/icon',
        'webViewLink' => 'https://drive.test/file/group-file-1',
        'size' => '1024',
        'modifiedTime' => '2024-05-01T12:00:00Z',
        'createdTime' => '2024-05-01T10:00:00Z',
    ]);

    $groupSubfolder = new DriveFile([
        'id' => 'group-sub-1',
        'name' => 'Actas',
    ]);

    $containerFile = new DriveFile([
        'id' => 'container-file-1',
        'name' => 'Contrato marco.docx',
        'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'iconLink' => 'https://example.com/icon-doc',
        'webViewLink' => 'https://drive.test/file/container-file-1',
        'size' => '2048',
        'modifiedTime' => '2024-04-20T09:00:00Z',
        'createdTime' => '2024-04-19T09:00:00Z',
    ]);

    $containerSubfolder = new DriveFile([
        'id' => 'container-sub-1',
        'name' => 'Referencias',
    ]);

    $driveService = Mockery::mock(GoogleDriveService::class);
    $driveService->shouldReceive('listFilesInFolder')->with('group-folder-123')->once()->andReturn([$groupFile]);
    $driveService->shouldReceive('listSubfolders')->with('group-folder-123')->once()->andReturn([$groupSubfolder]);
    $driveService->shouldReceive('shareItem')->once()->with('group-folder-123', 'member@example.com', 'writer');
    $driveService->shouldReceive('getWebContentLink')->with('group-file-1')->andReturn('https://download.test/group-file-1');
    $driveService->shouldReceive('listFilesInFolder')->with('container-folder-1')->once()->andReturn([$containerFile]);
    $driveService->shouldReceive('listSubfolders')->with('container-folder-1')->once()->andReturn([$containerSubfolder]);
    $driveService->shouldReceive('getWebContentLink')->with('container-file-1')->andReturn(null);
    $driveService->shouldReceive('listFilesInFolder')->with('container-folder-2')->once()->andReturn([]);
    $driveService->shouldReceive('listSubfolders')->with('container-folder-2')->once()->andReturn([]);

    $groupDriveFolder = new GroupDriveFolder();
    $groupDriveFolder->id = 321;
    $groupDriveFolder->google_id = 'group-folder-123';
    $groupDriveFolder->name = 'Equipo Legal';

    $helper = Mockery::mock(OrganizationDriveHelper::class);
    $helper->shouldReceive('initDrive')->once()->with(Mockery::on(fn($org) => $org->is($organization)))->andReturn(null);
    $helper->shouldReceive('ensureGroupFolder')->once()->with(Mockery::on(fn($g) => $g->is($group)))->andReturn($groupDriveFolder);
    $helper->shouldReceive('getDrive')->andReturn($driveService);
    $helper->shouldReceive('ensureContainerFolder')
        ->once()
        ->with(Mockery::on(fn($g) => $g->is($group)), Mockery::on(fn($container) => $container->id === $containerWithoutFolder->id))
        ->andReturn([
            'id' => 'container-folder-2',
            'metadata' => ['name' => 'Onboarding'],
        ]);

    app()->instance(OrganizationDriveHelper::class, $helper);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.groups.documents.index', ['group' => $group->id]));

    $response->assertOk()
        ->assertJsonPath('folder.google_id', 'group-folder-123')
        ->assertJsonPath('permissions.can_manage', true)
        ->assertJsonPath('files.0.id', 'group-file-1')
        ->assertJsonPath('subfolders.0.id', 'group-sub-1')
        ->assertJsonPath('containers.0.id', $containerWithFolder->id)
        ->assertJsonPath('containers.0.files.0.id', 'container-file-1')
        ->assertJsonPath('containers.0.subfolders.0.id', 'container-sub-1')
        ->assertJsonPath('containers.1.id', $containerWithoutFolder->id)
        ->assertJsonPath('containers.1.folder_id', 'container-folder-2');

    $data = $response->json();
    $containerIds = collect($data['containers'] ?? [])->pluck('id');
    expect($containerIds)->toContain($containerWithFolder->id);
    expect($containerIds)->toContain($containerWithoutFolder->id);
    expect($containerIds)->not()->toContain($inactiveContainer->id);

    $containerWithoutFolder->refresh();
    expect($containerWithoutFolder->drive_folder_id)->toBe('container-folder-2');
});

test('non members cannot view group documents', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $intruder = User::factory()->create(['email' => 'intruder@example.com']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org Privada',
        'descripcion' => 'Demo',
        'admin_id' => $owner->id,
    ]);

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Equipo Comercial',
        'descripcion' => 'Grupo restringido',
    ]);

    DB::table('group_user')->insert([
        'id_grupo' => $group->id,
        'user_id' => $owner->id,
        'rol' => 'administrador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $helper = Mockery::mock(OrganizationDriveHelper::class);
    $helper->shouldReceive('initDrive')->never();
    app()->instance(OrganizationDriveHelper::class, $helper);

    $response = $this->actingAs($intruder, 'sanctum')
        ->getJson(route('api.groups.documents.index', ['group' => $group->id]));

    $response->assertForbidden();
});

test('invited members receive reader permissions when listing group documents', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $viewer = User::factory()->create(['email' => 'viewer@example.com']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org Viewer',
        'descripcion' => 'Demo',
        'admin_id' => $admin->id,
    ]);

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Equipo Creativo',
        'descripcion' => 'Grupo de contenidos',
    ]);

    DB::table('group_user')->insert([
        'id_grupo' => $group->id,
        'user_id' => $viewer->id,
        'rol' => 'invitado',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $groupFile = new DriveFile([
        'id' => 'group-file-view',
        'name' => 'Resumen semanal.pdf',
        'mimeType' => 'application/pdf',
        'iconLink' => 'https://example.com/icon-pdf',
        'webViewLink' => 'https://drive.test/file/group-file-view',
        'size' => '512',
        'modifiedTime' => '2024-03-15T12:00:00Z',
        'createdTime' => '2024-03-15T10:00:00Z',
    ]);

    $driveService = Mockery::mock(GoogleDriveService::class);
    $driveService->shouldReceive('listFilesInFolder')->with('group-folder-viewer')->once()->andReturn([$groupFile]);
    $driveService->shouldReceive('listSubfolders')->with('group-folder-viewer')->once()->andReturn([]);
    $driveService->shouldReceive('shareItem')->once()->with('group-folder-viewer', 'viewer@example.com', 'reader');
    $driveService->shouldReceive('getWebContentLink')->with('group-file-view')->andReturn('https://download.test/group-file-view');

    $groupDriveFolder = new GroupDriveFolder();
    $groupDriveFolder->id = 111;
    $groupDriveFolder->google_id = 'group-folder-viewer';
    $groupDriveFolder->name = 'Equipo Creativo';

    $helper = Mockery::mock(OrganizationDriveHelper::class);
    $helper->shouldReceive('initDrive')->once()->with(Mockery::on(fn($org) => $org->is($organization)))->andReturn(null);
    $helper->shouldReceive('ensureGroupFolder')->once()->with(Mockery::on(fn($g) => $g->is($group)))->andReturn($groupDriveFolder);
    $helper->shouldReceive('getDrive')->andReturn($driveService);
    $helper->shouldReceive('ensureContainerFolder')->never();

    app()->instance(OrganizationDriveHelper::class, $helper);

    $response = $this->actingAs($viewer, 'sanctum')
        ->getJson(route('api.groups.documents.index', ['group' => $group->id]));

    $response->assertOk()
        ->assertJsonPath('permissions.can_view', true)
        ->assertJsonPath('permissions.can_manage', false)
        ->assertJsonPath('files.0.id', 'group-file-view');
});
