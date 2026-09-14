<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOnboarding
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs('invitations.accept')) {
            return $next($request);
        }

        // 1. Check if user has any teams
        if ($user->teams()->count() === 0) {
            if (! $request->routeIs('teams.create') && ! $request->routeIs('teams.store') && ! $request->routeIs('logout')) {
                return redirect()->route('teams.create');
            }

            return $next($request);
        }

        // Ensure user has a current team set
        if (! $user->current_team_id) {
            $user->switchTeam($user->teams()->first());
        }

        $currentTeam = $user->currentTeam;

        // 2. Check if current team has any projects
        if ($currentTeam && $currentTeam->projects()->count() === 0) {
            if (! $request->routeIs('projects.create') && ! $request->routeIs('projects.store') &&
                ! $request->routeIs('teams.*') && ! $request->routeIs('logout')) {
                return redirect()->route('projects.create', ['current_team' => $currentTeam->slug]);
            }
        }

        return $next($request);
    }
}
