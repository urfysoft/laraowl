<?php

use App\Models\Project;
use App\Services\IngestService;
use App\Services\RecordService;

it('handles non numeric outgoing request status values without SQL errors', function () {
    $project = Project::factory()->create();

    // Ingested rather than inserted directly, so the rollups the overview now
    // reads from are written. The client always sends `_group`, which becomes
    // the fingerprint the host list groups on.
    app(IngestService::class)->ingest($project, [
        [
            't' => 'outgoing-request',
            '_group' => 'failed-status',
            'host' => 'api.example.com',
            'status' => 'failed',
            'duration' => 125,
        ],
        [
            't' => 'outgoing-request',
            '_group' => 'ok-status',
            'host' => 'api.example.com',
            'status_code' => 200,
            'duration' => 75,
        ],
    ]);

    $stats = app(RecordService::class)->getOutgoingRequestStats($project, '24h');

    expect($stats['overview'])
        ->toMatchArray([
            'total' => 2,
            'ok' => 1,
            'failed' => 0,
        ]);

    expect($stats['hosts']->total())->toBe(2);
});
