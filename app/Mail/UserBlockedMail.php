<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class UserBlockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public User $blockedBy,
        public string $reason,
        public bool $permanent,
        public ?Carbon $blockedUntil
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Actualización de acceso a tu cuenta Juntify')
            ->view('emails.users.account-blocked')
            ->with([
                'user' => $this->recipient,
                'admin' => $this->blockedBy,
                'reason' => $this->reason,
                'permanent' => $this->permanent,
                'blockedUntil' => $this->blockedUntil,
            ]);
    }
}
