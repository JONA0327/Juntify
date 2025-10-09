<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRoleChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public string $oldRole,
        public string $newRole,
        public User $changedBy
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Actualización de rol en Juntify')
            ->view('emails.users.role-changed')
            ->with([
                'user' => $this->recipient,
                'oldRole' => $this->oldRole,
                'newRole' => $this->newRole,
                'admin' => $this->changedBy,
            ]);
    }
}
