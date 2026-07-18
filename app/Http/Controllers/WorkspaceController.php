<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $workspaces = $request->user()
            ->workspaces()
            ->withCount('projects')
            ->latest()
            ->get();

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $workspace = Workspace::create([
            'owner_id'    => $request->user()->id,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        // Also add the creator as a member in the pivot
        $workspace->users()->attach($request->user()->id, ['role' => 'owner']);

        return redirect()->route('workspaces.index')->with('success', 'Workspace created.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        $this->authorize('owner', $workspace);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $workspace->update($validated);

        return back()->with('success', 'Workspace updated.');
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        $this->authorize('owner', $workspace);

        $workspace->delete();

        return redirect()->route('workspaces.index')->with('success', 'Workspace deleted.');
    }
}
