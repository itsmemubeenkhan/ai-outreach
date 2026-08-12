<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $q = Task::with('lead');
        $scope = $request->string('scope')->value();
        if ($scope === 'today') {
            $q->whereDate('due_at', today());
        } elseif ($scope === 'overdue') {
            $q->where('due_at', '<', now())->where('status', 'open');
        } elseif ($scope === 'upcoming') {
            $q->where('due_at', '>', now())->where('status', 'open');
        }

        return view('tasks.index', ['tasks' => $q->orderByRaw("FIELD(priority,'urgent','high','normal','low')")->orderBy('due_at')->paginate(25)]);
    }

    public function complete(Task $task)
    {
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $task->lead->activities()->create(['type' => 'task_completed', 'description' => 'Task completed: '.$task->title]);

        return back()->with('success', 'Task completed.');
    }
}
