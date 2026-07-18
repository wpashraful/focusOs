<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractTextJob;
use App\Models\Project;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ResourceController extends Controller
{
    /**
     * Show all uploaded resources for a project.
     */
    public function index(Project $project)
    {
        $resources = $project->chunks()->with('resource')
            ->get()
            ->pluck('resource')
            ->unique('id')
            ->values();

        // If above pluck is too complex, just load hasMany
        $resources = Resource::where('project_id', $project->id)
            ->latest()
            ->get();

        return Inertia::render('Resources/Index', [
            'project'   => $project,
            'resources' => $resources,
        ]);
    }

    /**
     * Handle file uploads and dispatch extraction pipeline.
     */
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB Limit
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('project_resources/' . $project->id);

        $resource = Resource::create([
            'project_id' => $project->id,
            'name'       => $fileName,
            'file_path'  => $filePath,
            'mime_type'  => $file->getClientMimeType(),
            'status'     => 'processing',
        ]);

        // Kick off asynchronous ingestion job chain
        ExtractTextJob::dispatch($resource);

        return back()->with('success', 'File uploaded. Processing text...');
    }

    /**
     * Delete resource.
     */
    public function destroy(Project $project, Resource $resource)
    {
        // Delete actual storage file
        if (Storage::exists($resource->file_path)) {
            Storage::delete($resource->file_path);
        }

        $resource->delete();

        return back()->with('success', 'Resource deleted.');
    }
}
