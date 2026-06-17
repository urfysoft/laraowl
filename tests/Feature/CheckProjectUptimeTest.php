<?php

use App\Models\Project;
use App\Models\Team;
use App\Services\AlertService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it logs up when the check succeeds on the first try', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->create([
        'team_id' => $team->id,
        'url' => 'https://example.com',
        'last_uptime_status' => null,
    ]);

    Http::fake([
        '*' => Http::response('OK', 200),
    ]);

    $alertService = Mockery::mock(AlertService::class);
    $alertService->shouldNotReceive('notifyUptimeDown');
    $this->instance(AlertService::class, $alertService);

    $this->artisan('projects:check-health')
        ->assertExitCode(0);

    $project->refresh();
    expect($project->last_uptime_status)->toBe('up');
    expect($project->uptimeChecks)->toHaveCount(1);
    expect($project->uptimeChecks->first()->status)->toBe('up');
});

test('it logs up when the check fails on the first try but succeeds on the retry', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->create([
        'team_id' => $team->id,
        'url' => 'https://example.com',
        'last_uptime_status' => null,
    ]);

    $attempts = 0;
    Http::fake([
        '*' => function ($request) use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                return new RejectedPromise(
                    new ConnectException(
                        'cURL error 28: Operation timed out',
                        new Request('GET', $request->url())
                    )
                );
            }

            return Http::response('OK', 200);
        },
    ]);

    $alertService = Mockery::mock(AlertService::class);
    $alertService->shouldNotReceive('notifyUptimeDown');
    $this->instance(AlertService::class, $alertService);

    $this->artisan('projects:check-health')
        ->assertExitCode(0);

    $project->refresh();
    expect($project->last_uptime_status)->toBe('up');
    expect($project->uptimeChecks)->toHaveCount(1);
    expect($project->uptimeChecks->first()->status)->toBe('up');
});

test('it logs down and sends alert when all attempts fail', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->create([
        'team_id' => $team->id,
        'url' => 'https://example.com',
        'last_uptime_status' => 'up',
    ]);

    Http::fake([
        '*' => function ($request) {
            return new RejectedPromise(
                new ConnectException(
                    'cURL error 28: Operation timed out',
                    new Request('GET', $request->url())
                )
            );
        },
    ]);

    $alertService = Mockery::mock(AlertService::class);
    $alertService->shouldReceive('notifyUptimeDown')
        ->once()
        ->with(Mockery::on(function ($p) use ($project) {
            return $p->id === $project->id;
        }), 0, 'cURL error 28: Operation timed out');
    $this->instance(AlertService::class, $alertService);

    $this->artisan('projects:check-health')
        ->assertExitCode(0);

    $project->refresh();
    expect($project->last_uptime_status)->toBe('down');
    expect($project->uptimeChecks)->toHaveCount(1);
    expect($project->uptimeChecks->first()->status)->toBe('down');
});
