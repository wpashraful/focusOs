<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user           = $request->user();
        $currentProject = null;

        if ($user) {
            $currentProject = $user->workspaces()->with('projects')
                ->get()->pluck('projects')->flatten()
                ->where('status', 'active')->first();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'currentProject' => $currentProject ? $currentProject->only('id', 'name', 'current_phase_name', 'current_phase_goal', 'phase_ends_at') : null,
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],
        ];
    }
}
