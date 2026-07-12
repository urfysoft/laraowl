<?php

use App\Models\Project;
use App\Models\Record;

function seedRecordAgedDays(Project $project, int $days): void
{
    Record::create([
        'project_id' => $project->id,
        'type' => 'request',
        'fingerprint' => 'f',
        'payload' => ['status_code' => 200],
        'created_at' => now()->subDays($days),
    ]);
}

test('an unrecognised period falls back to a bounded window instead of scanning everything', function (?string $period) {
    $project = Project::factory()->create();
    seedRecordAgedDays($project, 3);

    // This used to return the query unfiltered, turning every such call into a
    // full table scan and reporting records from outside the requested window.
    expect(Record::query()->forPeriod($period)->count())->toBe(0);
})->with([null, '', 'bogus', 'all', '24h']);

test('known periods keep their windows', function () {
    $project = Project::factory()->create();
    seedRecordAgedDays($project, 3);

    expect(Record::query()->forPeriod('1h')->count())->toBe(0)
        ->and(Record::query()->forPeriod('7d')->count())->toBe(1)
        ->and(Record::query()->forPeriod('30d')->count())->toBe(1);
});

test('a custom period uses the supplied bounds', function () {
    $project = Project::factory()->create();
    seedRecordAgedDays($project, 3);

    $from = now()->subDays(4)->toDateTimeString();
    $to = now()->subDays(2)->toDateTimeString();

    expect(Record::query()->forPeriod('custom', $from, $to)->count())->toBe(1)
        ->and(Record::query()->forPeriod('custom', now()->subDay()->toDateTimeString(), now()->toDateTimeString())->count())->toBe(0);
});

test('every period still applies a created_at bound', function (?string $period) {
    $sql = Record::query()->forPeriod($period)->toSql();

    expect($sql)->toContain('created_at');
})->with([null, 'bogus', '1h', '24h', '7d', '14d', '30d']);
