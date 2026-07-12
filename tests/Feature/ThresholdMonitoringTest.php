<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Project;
use App\Models\Team;
use App\Models\Threshold;
use App\Services\AlertService;
use App\Services\IngestService;
use App\Services\RollupWriter;
use App\Services\SecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ThresholdMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_issue_when_threshold_is_exceeded()
    {
        // 1. Setup Data
        $team = Team::factory()->create();
        $project = Project::factory()->create(['team_id' => $team->id]);

        // 2. Create a Threshold
        $threshold = Threshold::create([
            'project_id' => $project->id,
            'type' => 'route',
            'key' => '/api/test',
            'value' => 200, // 200ms
            'is_enabled' => true,
        ]);

        // 3. Mock AlertService to verify notification
        $alertService = Mockery::mock(AlertService::class);
        $alertService->shouldReceive('notifySlowPerformance')
            ->once()
            ->with(Mockery::type(Issue::class));

        // 4. Ingest a record that exceeds threshold
        $ingestService = new IngestService($alertService, app(SecurityService::class), app(RollupWriter::class));

        $records = [
            [
                't' => 'request',
                'path' => '/api/test',
                'duration' => 500_000, // 500ms in microseconds, exceeds the 200ms threshold
                'method' => 'GET',
            ],
        ];

        $ingestService->ingest($project, $records);

        // 5. Verify Issue creation
        $this->assertDatabaseHas('issues', [
            'project_id' => $project->id,
            'title' => 'Slow Route: /api/test',
        ]);

        $issue = Issue::where('project_id', $project->id)->first();
        $this->assertStringContainsString('Duration: 500ms', $issue->message);
        $this->assertStringContainsString('Threshold: 200ms', $issue->message);

        // 6. Verify Record is linked to Issue
        $this->assertDatabaseHas('records', [
            'project_id' => $project->id,
            'type' => 'request',
            'issue_id' => $issue->id,
        ]);
    }

    public function test_it_does_not_create_an_issue_when_within_threshold()
    {
        $team = Team::factory()->create();
        $project = Project::factory()->create(['team_id' => $team->id]);

        Threshold::create([
            'project_id' => $project->id,
            'type' => 'route',
            'key' => '/api/safe',
            'value' => 500,
            'is_enabled' => true,
        ]);

        $alertService = Mockery::mock(AlertService::class);
        $alertService->shouldNotReceive('notifySlowPerformance');

        $ingestService = new IngestService($alertService, app(SecurityService::class), app(RollupWriter::class));

        $records = [
            [
                't' => 'request',
                'path' => '/api/safe',
                'duration' => 100_000, // 100ms in microseconds, within the 500ms threshold
                'method' => 'GET',
            ],
        ];

        $ingestService->ingest($project, $records);

        $this->assertDatabaseMissing('issues', [
            'project_id' => $project->id,
            'title' => 'Slow Route: /api/safe',
        ]);
    }

    public function test_a_threshold_is_compared_in_the_same_unit_as_the_duration()
    {
        $team = Team::factory()->create();
        $project = Project::factory()->create(['team_id' => $team->id]);

        Threshold::create([
            'project_id' => $project->id,
            'type' => 'route',
            'key' => '/api/edge',
            'value' => 500, // 500 milliseconds
            'is_enabled' => true,
        ]);

        $alertService = Mockery::mock(AlertService::class);
        $alertService->shouldReceive('notifySlowPerformance')->once();
        $ingestService = new IngestService($alertService, app(SecurityService::class), app(RollupWriter::class));

        // 499ms then 501ms, both in microseconds. The old code compared the raw
        // microseconds against 500, so 499_000 already "exceeded" 500.
        $ingestService->ingest($project, [
            ['t' => 'request', 'path' => '/api/edge', 'duration' => 499_000, 'method' => 'GET'],
        ]);
        $this->assertDatabaseMissing('issues', ['project_id' => $project->id, 'title' => 'Slow Route: /api/edge']);

        $ingestService->ingest($project, [
            ['t' => 'request', 'path' => '/api/edge', 'duration' => 501_000, 'method' => 'GET'],
        ]);
        $this->assertDatabaseHas('issues', ['project_id' => $project->id, 'title' => 'Slow Route: /api/edge']);
    }
}
