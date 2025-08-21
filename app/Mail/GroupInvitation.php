<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GroupInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $groupId;

    public function __construct(string $code, int $groupId)
    {
        $this->code = $code;
        $this->groupId = $groupId;
    }

    public function build()
    {
        $url = url('/register?code=' . $this->code . '&group=' . $this->groupId);

        return $this->subject('Invitación a unirse a un grupo')
            ->view('emails.group-invitation')
            ->with(['url' => $url]);
    }
}
