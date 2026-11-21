<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = auth()->user()
            ->projects()
            ->withCount('websites')
            ->latest('id')
            ->get();

        return view('projects.index', ['projects' => $projects]);
    }

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = auth()->user()->projects()->create($request->validated());

        AuditLog::record('project.created', auth()->id(), $project, ['name' => $project->name], $request->ip(), $request->userAgent());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project created.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $websites = $project->websites()
            ->with(['latestAnalysisJob'])
            ->latest('id')
            ->get();

        return view('projects.show', [
            'project' => $project,
            'websites' => $websites,
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', ['project' => $project]);
    }

    public function update(StoreProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        AuditLog::record('project.updated', auth()->id(), $project, [], $request->ip(), $request->userAgent());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        AuditLog::record('project.deleted', auth()->id(), null, ['project_id' => $project->id, 'name' => $project->name], $request->ip(), $request->userAgent());

        return redirect()
            ->route('projects.index')
            ->with('status', 'Project deleted.');
    }
}
