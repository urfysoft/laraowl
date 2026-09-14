<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\IngestService;

test('exception detail views receive a normalized stack trace', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $project = Project::factory()->create(['team_id' => $team->id]);

    app(IngestService::class)->ingest($project, [[
        't' => 'exception',
        '_group' => 'checkout-exception',
        'class' => 'RuntimeException',
        'message' => 'Payment failed',
        'trace' => json_encode([[
            'file' => 'app/Services/Checkout.php:42',
            'source' => 'App\\Services\\Checkout->charge(string)',
            'code' => ['42' => 'throw new RuntimeException;'],
        ]], JSON_THROW_ON_ERROR),
    ]]);

    $routeParameters = [
        'current_team' => $team->slug,
        'project' => $project->slug,
    ];

    $this->actingAs($user)
        ->get(route('exceptions.show', [...$routeParameters, 'hash' => 'checkout-exception']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/exceptions/show')
            ->where('meta.stack.0.file', 'app/Services/Checkout.php')
            ->where('meta.stack.0.line', 42)
            ->where('meta.stack.0.function', 'charge')
            ->where('meta.stack.0.preview.42', 'throw new RuntimeException;')
        );

    $issue = $project->issues()->firstOrFail();

    $this->actingAs($user)
        ->get(route('issues.show', [...$routeParameters, 'issue' => $issue->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/issues/show')
            ->where('issue.records.0.payload.stack.0.file', 'app/Services/Checkout.php')
            ->where('issue.records.0.payload.stack.0.line', 42)
            ->where('issue.records.0.payload.stack.0.snippet', 'throw new RuntimeException;')
        );
});
