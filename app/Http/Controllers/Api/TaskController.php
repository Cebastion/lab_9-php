<?php

namespace App\Http\Controllers\Api;

use App\Events\TaskCreated;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        return response()->json(Task::all());
    }

    public function show(Task $task)
    {
        return response()->json($task);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|string|in:new,in_progress,done',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $task = Task::create($validated);

        // Генеруємо подію після збереження завдання
        TaskCreated::dispatch($task);

        return response()->json($task, 201);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|string|in:new,in_progress,done',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        // update() на екземплярі моделі — щоб спрацював Observer
        $task->update($validated);

        return response()->json($task);
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json(null, 204);
    }
}
