<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Створюємо екземпляр події.
     */
    public function __construct(public Task $task)
    {
    }
}
