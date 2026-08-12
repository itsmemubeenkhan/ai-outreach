<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function due()
    {
        $tasks = Task::with('lead')->where('status', 'open')->where('due_at', '<=', now())
            ->where(fn ($query) => $query->where('user_id', auth()->id())->when(auth()->user()->isAdmin(), fn ($q) => $q->orWhereNull('user_id')))
            ->orderBy('due_at')->limit(10)->get();

        return response()->json(['tasks' => $tasks->map(fn ($task) => [
            'id' => $task->id, 'title' => $task->title,
            'lead' => $task->lead->business_name ?: $task->lead->phone,
            'due_at' => $task->due_at?->toIso8601String(),
        ])]);
    }

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
        abort_unless(auth()->user()->isAdmin() || $task->user_id === auth()->id(), 403);
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $task->lead->activities()->create(['type' => 'task_completed', 'description' => 'Task completed: '.$task->title]);

        return back()->with('success', 'Task completed.');
    }
}
