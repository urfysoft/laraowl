<?php

use App\Models\Project;
use App\Models\RecordRollup;
use App\Models\RecordUserBucket;

test('rollups outlive raw records so long range charts keep working', function () {
    // Refreshed, because the database defaults are not hydrated onto the model
    // the factory just inserted.
    $project = Project::factory()->create()->fresh();

    expect($project->retention_days)->toBe(7)
        ->and($project->rollup_retention_days)->toBe(90);
});

test('buckets past the rollup retention window are pruned', function () {
    $project = Project::factory()->create(['rollup_retention_days' => 90]);

    $stale = RecordRollup::create([
        'project_id' => $project->id,
        'type' => 'request',
        'bucket' => now()->subDays(120),
        'count' => 1,
    ]);

    $fresh = RecordRollup::create([
        'project_id' => $project->id,
        'type' => 'request',
        'bucket' => now()->subDays(30),
        'count' => 1,
    ]);

    $this->artisan('model:prune', ['--model' => [RecordRollup::class]])->assertExitCode(0);

    expect(RecordRollup::find($stale->id))->toBeNull()
        ->and(RecordRollup::find($fresh->id))->not->toBeNull();
});

test('user buckets are pruned on the same window', function () {
    $project = Project::factory()->create(['rollup_retention_days' => 90]);

    $stale = RecordUserBucket::create([
        'project_id' => $project->id,
        'type' => 'request',
        'bucket' => now()->subDays(120),
        'user_key' => '1',
    ]);

    $fresh = RecordUserBucket::create([
        'project_id' => $project->id,
        'type' => 'request',
        'bucket' => now()->subDays(1),
        'user_key' => '1',
    ]);

    $this->artisan('model:prune', ['--model' => [RecordUserBucket::class]])->assertExitCode(0);

    expect(RecordUserBucket::find($stale->id))->toBeNull()
        ->and(RecordUserBucket::find($fresh->id))->not->toBeNull();
});

test('a project with retention disabled keeps its rollups', function () {
    $project = Project::factory()->create(['rollup_retention_days' => 0]);

    $ancient = RecordRollup::create([
        'project_id' => $project->id,
        'type' => 'request',
        'bucket' => now()->subYears(2),
        'count' => 1,
    ]);

    $this->artisan('model:prune', ['--model' => [RecordRollup::class]])->assertExitCode(0);

    expect(RecordRollup::find($ancient->id))->not->toBeNull();
});
