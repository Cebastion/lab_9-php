<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotifyAssignee
{
    /**
     * Обробка події TaskCreated.
     */
    public function handle(TaskCreated $event): void
    {
        $task = $event->task;

        // Виконавця не призначено — нічого не робимо
        if (empty($task->assigned_to)) {
            return;
        }

        $assignee = User::find($task->assigned_to);

        Log::info('NotifyAssignee: сповіщення виконавця про нове завдання', [
            'task_id'       => $task->id,
            'task_title'    => $task->title,
            'assignee_id'   => $task->assigned_to,
            'assignee_name' => $assignee?->name,
            'message'       => "Завдання «{$task->title}» призначено користувачу "
                               . ($assignee?->name ?? "#{$task->assigned_to}"),
        ]);
    }
}
