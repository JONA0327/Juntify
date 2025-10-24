<?php

namespace App\Mail;

use App\Models\TaskLaravel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TaskLaravel $task,
        public User $owner,
        public User $assignee,
        public ?string $message = null
    ) {
    }

    public function build(): self
    {
        return $this->subject('Te han asignado una nueva tarea en Juntify')
            ->view('emails.tasks.assigned')
            ->with([
                'task' => $this->task,
                'owner' => $this->owner,
                'assignee' => $this->assignee,
                'message' => $this->message,
                'acceptUrl' => route('tasks.respond', ['task' => $this->task->id, 'action' => 'accept']) . '?token=' . $this->assignee->id,
                'rejectUrl' => route('tasks.respond', ['task' => $this->task->id, 'action' => 'reject']) . '?token=' . $this->assignee->id,
            ]);
    }
}
