<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GroupInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public string $inviterName;
    public string $organizationName;
    public ?string $groupName;
    public string $groupCode;
    public string $signupUrl;

    public function __construct(string $inviterName, string $organizationName, ?string $groupName, string $groupCode, string $signupUrl)
    {
        $this->inviterName = $inviterName;
        $this->organizationName = $organizationName;
        $this->groupName = $groupName;
        $this->groupCode = $groupCode;
        $this->signupUrl = $signupUrl;
    }

    public function build()
    {
        return $this->subject('Invitación a unirte a un grupo en Juntify')
            ->view('emails.group-invitation')
            ->with([
                'inviterName' => $this->inviterName,
                'organizationName' => $this->organizationName,
                'groupName' => $this->groupName,
                'groupCode' => $this->groupCode,
                'signupUrl' => $this->signupUrl,
            ]);
    }
}
