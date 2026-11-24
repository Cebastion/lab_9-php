<?php

namespace App\Observers;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

class TaskObserver
{
    /**
     * Завдання створено.
     */
    public function created(Task $task): void
    {
        Log::info('TaskObserver: створено нове завдання', [
            'task_id' => $task->id,
            'title'   => $task->title,
        ]);
    }

    /**
     * Завдання оновлено.
     */
    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            Log::info('TaskObserver: змінено статус завдання', [
                'task_id'    => $task->id,
                'old_status' => $task->getOriginal('status'),
                'new_status' => $task->status,
            ]);
        }
    }

    /**
     * Завдання видалено.
     */
    public function deleted(Task $task): void
    {
        Log::info('TaskObserver: видалено завдання', [
            'task_id' => $task->id,
            'title'   => $task->title,
        ]);
    }
}
