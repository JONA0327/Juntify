<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Contact;
use App\Services\GoogleServiceAccount;

uses(RefreshDatabase::class);

class FakeServiceAccount
{
    public ?string $lastSharedItemId = null;
    public ?string $lastSharedEmail = null;
    public bool $failOnFileGet = false;

    public function impersonate($email) {}

    public function shareItem($itemId, $email, $role = 'reader')
    {
        $this->lastSharedItemId = $itemId;
        $this->lastSharedEmail = $email;
    }

    public function getDrive()
    {
        $parent = $this;
        return new class($parent) {
            private $parent;
            public $files;
            public $permissions;
            public function __construct($parent)
            {
                $this->parent = $parent;
                $this->files = new class($parent) {
                    private $parent;
                    public function __construct($parent){ $this->parent = $parent; }
                    public function get($id, $opts)
                    {
                        if ($this->parent->failOnFileGet && $id === 'invalid') {
                            throw new Exception('not found');
                        }
                        return (object)['id' => $id];
                    }
                };
                $this->permissions = new class($parent) {
                    private $parent;
                    public function __construct($parent){ $this->parent = $parent; }
                    public function listPermissions($itemId, $opts)
                    {
                        if ($this->parent->lastSharedItemId === $itemId && $this->parent->lastSharedEmail) {
                            $email = $this->parent->lastSharedEmail;
                            return new class($email) {
                                private $email;
                                public function __construct($email){ $this->email = $email; }
                                public function getPermissions()
                                {
                                    return [new class($this->email) {
                                        private $email;
                                        public function __construct($email){ $this->email = $email; }
                                        public function getEmailAddress(){ return $this->email; }
                                    }];
                                }
                            };
                        }
                        return new class {
                            public function getPermissions(){ return []; }
                        };
                    }
                };
            }
        };
    }
}

test('sharing meeting grants drive access when transcript id is valid', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $recipient = User::factory()->create(['email' => 'recipient@example.com']);

    Contact::create(['user_id' => $owner->id, 'contact_id' => $recipient->id]);

    $meeting = createLegacyMeeting($owner, [
        'meeting_name' => 'Legacy',
        'transcript_drive_id' => 'file123',
    ]);

    $sa = new FakeServiceAccount();
    app()->instance(GoogleServiceAccount::class, $sa);

    $this->actingAs($owner, 'sanctum')->postJson('/api/shared-meetings/share', [
        'meeting_id' => $meeting->id,
        'contact_ids' => [$recipient->id],
    ])->assertOk();

    expect($sa->lastSharedItemId)->toBe('file123');
    expect($sa->lastSharedEmail)->toBe('recipient@example.com');
});

test('sharing meeting skips drive access when transcript id is invalid', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $recipient = User::factory()->create(['email' => 'recipient@example.com']);

    Contact::create(['user_id' => $owner->id, 'contact_id' => $recipient->id]);

    $meeting = createLegacyMeeting($owner, [
        'meeting_name' => 'Legacy',
        'transcript_drive_id' => 'invalid',
    ]);

    $sa = new FakeServiceAccount();
    $sa->failOnFileGet = true;
    app()->instance(GoogleServiceAccount::class, $sa);

    $this->actingAs($owner, 'sanctum')->postJson('/api/shared-meetings/share', [
        'meeting_id' => $meeting->id,
        'contact_ids' => [$recipient->id],
    ])->assertOk();

    expect($sa->lastSharedItemId)->toBeNull();
});
