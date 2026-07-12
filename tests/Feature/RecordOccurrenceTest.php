<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Record;
use App\Models\Team;
use App\Models\User;
use App\Services\IngestService;

/**
 * Log in a user who belongs to the project's team, and return both.
 */
function actingMember(): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $project = Project::factory()->create(['team_id' => $team->id]);

    return [$user, $team, $project];
}

test('the trace id is lifted into an indexed column at ingest', function () {
    [, , $project] = actingMember();

    app(IngestService::class)->ingest($project, [
        ['t' => 'request', 'status_code' => 200, 'trace_id' => 'abc-123'],
        ['t' => 'query', 'sql' => 'select 1', 'duration' => 10, 'trace_id' => 'abc-123'],
        ['t' => 'log', 'level' => 'info', 'message' => 'hi'],
    ]);

    $request = Record::where('project_id', $project->id)->where('type', 'request')->first();
    $query = Record::where('project_id', $project->id)->where('type', 'query')->first();
    $log = Record::where('project_id', $project->id)->where('type', 'log')->first();

    expect($request->trace_id)->toBe('abc-123')
        ->and($query->trace_id)->toBe('abc-123')
        ->and($log->trace_id)->toBeNull();
});

test('the occurrence page loads the queries and events sharing the trace', function () {
    [$user, $team, $project] = actingMember();

    app(IngestService::class)->ingest($project, [
        ['t' => 'request', 'status_code' => 200, 'route_path' => '/checkout', 'queries' => 2, 'trace_id' => 't-1'],
        ['t' => 'query', 'sql' => 'select * from carts', 'duration' => 1200, 'trace_id' => 't-1'],
        ['t' => 'query', 'sql' => 'update carts set total = ?', 'duration' => 800, 'trace_id' => 't-1'],
        // A different request's query must not leak into this trace.
        ['t' => 'query', 'sql' => 'select 999', 'duration' => 5, 'trace_id' => 't-2'],
    ]);

    $request = Record::where('project_id', $project->id)->where('type', 'request')->first();

    $this->actingAs($user)
        ->get(route('records.show', [
            'current_team' => $team->slug,
            'project' => $project->slug,
            'record' => $request->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/records/show')
            ->where('record.id', $request->id)
            ->has('relatedRecords', 2)
            ->where('relatedRecords.0.payload.sql', 'select * from carts')
            ->where('relatedRecords.1.payload.sql', 'update carts set total = ?')
        );
});

test('a request with no trace exposes no related records', function () {
    [$user, $team, $project] = actingMember();

    app(IngestService::class)->ingest($project, [
        ['t' => 'request', 'status_code' => 200, 'route_path' => '/'],
    ]);

    $request = Record::where('project_id', $project->id)->first();

    $this->actingAs($user)
        ->get(route('records.show', [
            'current_team' => $team->slug,
            'project' => $project->slug,
            'record' => $request->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('relatedRecords', 0));
});

test('a member cannot open a record belonging to another teams project', function () {
    [$attacker, $attackerTeam, $attackerProject] = actingMember();

    // A different tenant's project with a record the attacker must never see.
    [, , $victimProject] = actingMember();
    app(IngestService::class)->ingest($victimProject, [
        ['t' => 'request', 'status_code' => 200, 'route_path' => '/secret', 'ip' => '10.9.9.9'],
    ]);
    $victimRecord = Record::where('project_id', $victimProject->id)->firstOrFail();

    // The attacker points their own project's route at the victim's record id.
    $this->actingAs($attacker)
        ->get(route('records.show', [
            'current_team' => $attackerTeam->slug,
            'project' => $attackerProject->slug,
            'record' => $victimRecord->id,
        ]))
        ->assertNotFound();
});

test('the backfill lifts trace_id out of legacy payloads', function () {
    [, , $project] = actingMember();

    Record::create([
        'project_id' => $project->id,
        'type' => 'request',
        'fingerprint' => 'f',
        'payload' => ['status_code' => 200, 'trace_id' => 'legacy-trace'],
        'created_at' => now(),
    ]);

    expect(Record::where('project_id', $project->id)->value('trace_id'))->toBeNull();

    $this->artisan('laraowl:rollups:backfill')->assertExitCode(0);

    expect(Record::where('project_id', $project->id)->value('trace_id'))->toBe('legacy-trace');
});
