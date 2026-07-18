<?php

namespace App\Http\Controllers;

use App\Models\FutureIdea;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FutureIdeasController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->user()
            ->workspaces()->with('projects')
            ->get()->pluck('projects')->flatten()
            ->where('status', 'active')->first();

        $ideas = FutureIdea::where('user_id', $request->user()->id)
            ->when($project, fn($q) => $q->where('project_id', $project->id))
            ->orderByRaw("FIELD(status, 'promoted', 'reviewed', 'pending')")
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('FutureIdeas/Index', [
            'ideas'   => $ideas,
            'project' => $project?->only('id', 'name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string|max:2000',
        ]);

        $project = $request->user()
            ->workspaces()->with('projects')
            ->get()->pluck('projects')->flatten()
            ->where('status', 'active')->first();

        FutureIdea::create([
            'user_id'    => $request->user()->id,
            'project_id' => $project?->id,
            'title'      => $validated['title'],
            'content'    => $validated['content'] ?? null,
            'status'     => 'pending',
        ]);

        return redirect()->back()->with('success', 'Idea saved!');
    }

    public function update(Request $request, FutureIdea $idea)
    {
        $this->authorize('update', $idea);

        $validated = $request->validate([
            'status' => 'required|in:pending,reviewed,promoted',
            'notes'  => 'nullable|string|max:2000',
        ]);

        $idea->update($validated);

        return redirect()->back()->with('success', 'Idea updated!');
    }

    public function destroy(FutureIdea $idea)
    {
        $this->authorize('delete', $idea);
        $idea->delete();
        return redirect()->back()->with('success', 'Idea removed.');
    }
}
