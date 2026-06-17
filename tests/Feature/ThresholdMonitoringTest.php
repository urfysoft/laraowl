<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Project;
use App\Models\Team;
use App\Models\Threshold;
use App\Services\AlertService;
use App\Services\IngestService;
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
        $ingestService = new IngestService($alertService, app(SecurityService::class));

        $records = [
            [
                't' => 'request',
                'path' => '/api/test',
                'duration' => 500, // Exceeds 200ms
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

        $ingestService = new IngestService($alertService, app(SecurityService::class));

        $records = [
            [
                't' => 'request',
                'path' => '/api/safe',
                'duration' => 100, // Within 500ms
                'method' => 'GET',
            ],
        ];

        $ingestService->ingest($project, $records);

        $this->assertDatabaseMissing('issues', [
            'project_id' => $project->id,
            'title' => 'Slow Route: /api/safe',
        ]);
    }
}
