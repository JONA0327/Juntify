<?php

namespace App\Mail;

use App\Models\TaskLaravel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskReactivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TaskLaravel $task,
        public User $owner,
        public ?string $reason = null
    ) {
    }

    public function build(): self
    {
        return $this->subject('Tarea reactivada en Juntify')
            ->view('emails.tasks.reactivated')
            ->with([
                'task' => $this->task,
                'owner' => $this->owner,
                'reason' => $this->reason,
            ]);
    }
}
