<?php

namespace App\Mail;

use App\Models\TaskLaravel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TaskLaravel $task,
        public User $owner,
        public User $rejector,
        public ?string $reason = null
    ) {
    }

    public function build(): self
    {
        return $this->subject('Tu tarea ha sido rechazada')
            ->view('emails.tasks.rejected')
            ->with([
                'task' => $this->task,
                'owner' => $this->owner,
                'rejector' => $this->rejector,
                'reason' => $this->reason,
                'taskUrl' => route('tareas.index') . '#task-' . $this->task->id,
            ]);
    }
}
