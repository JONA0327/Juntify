<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\SharedMeeting;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskAssignableUsersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_includes_contacts_organization_members_and_shared_users_without_duplicates(): void
    {
        $organizationId = 101;
        $owner = User::factory()->create([
            'current_organization_id' => $organizationId,
        ]);

        $meeting = TranscriptionLaravel::factory()
            ->state(['username' => $owner->username])
            ->create();

        $contactOnly = User::factory()->create();
        $sharedContact = User::factory()->create();

        Contact::create([
            'id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'contact_id' => $contactOnly->id,
        ]);

        Contact::create([
            'id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'contact_id' => $sharedContact->id,
        ]);

        $organizationMember = User::factory()->create([
            'current_organization_id' => $organizationId,
        ]);

        $sharedOnly = User::factory()->create();
        $pendingShared = User::factory()->create();

        SharedMeeting::create([
            'meeting_id' => $meeting->id,
            'shared_by' => $owner->id,
            'shared_with' => $sharedOnly->id,
            'status' => 'accepted',
        ]);

        SharedMeeting::create([
            'meeting_id' => $meeting->id,
            'shared_by' => $owner->id,
            'shared_with' => $sharedContact->id,
            'status' => 'accepted',
        ]);

        SharedMeeting::create([
            'meeting_id' => $meeting->id,
            'shared_by' => $owner->id,
            'shared_with' => $pendingShared->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->getJson(route('api.tasks-laravel.assignable-users', [
            'meeting_id' => $meeting->id,
        ]));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $users = collect($response->json('users'));

        $this->assertTrue($users->contains(fn ($user) => $user['id'] === $contactOnly->id && $user['source'] === 'contact'));
        $this->assertTrue($users->contains(fn ($user) => $user['id'] === $organizationMember->id && $user['source'] === 'organization'));
        $this->assertTrue($users->contains(fn ($user) => $user['id'] === $sharedOnly->id && $user['source'] === 'shared'));

        $sharedContactEntry = $users->firstWhere('id', $sharedContact->id);
        $this->assertNotNull($sharedContactEntry);
        $this->assertSame('contact', $sharedContactEntry['source']);

        $this->assertFalse($users->contains(fn ($user) => $user['id'] === $pendingShared->id));
        $this->assertFalse($users->contains(fn ($user) => $user['id'] === $owner->id));
    }

    /** @test */
    public function it_uses_full_name_when_assigning_by_user_id_on_store(): void
    {
        $owner = User::factory()->create();
        $meeting = TranscriptionLaravel::factory()
            ->state(['username' => $owner->username])
            ->create();

        $assignee = User::factory()->create([
            'full_name' => 'Assigned Person',
        ]);

        $response = $this->actingAs($owner)->postJson(route('api.tasks-laravel.tasks.store'), [
            'tarea' => 'Nueva tarea',
            'meeting_id' => $meeting->id,
            'assigned_user_id' => $assignee->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('task.assigned_user_id', (string) $assignee->id);
        $response->assertJsonPath('task.asignado', $assignee->full_name);
        $response->assertJsonPath('task.assignment_status', 'pending');

        $this->assertDatabaseHas('tasks_laravel', [
            'id' => $response->json('task.id'),
            'assigned_user_id' => (string) $assignee->id,
            'asignado' => $assignee->full_name,
            'assignment_status' => 'pending',
        ]);
    }
}
