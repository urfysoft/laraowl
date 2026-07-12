<?php

use App\Enums\TeamRole;
use App\Models\AlertRule;
use App\Models\Integration;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Team;
use App\Models\Threshold;
use App\Models\User;

/**
 * A user, the team they belong to, and a project in it.
 */
function tenant(): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $project = Project::factory()->create(['team_id' => $team->id]);

    return [$user, $team, $project];
}

/**
 * The URL params that place a victim resource under the attacker's own project.
 */
function crossTenant(array $attacker, Project $victimProject): array
{
    [, $attackerTeam, $attackerProject] = $attacker;

    return [
        'current_team' => $attackerTeam->slug,
        'project' => $attackerProject->slug,
    ];
}

test('a member cannot read another teams issue by id', function () {
    $attacker = tenant();
    [, , $victimProject] = tenant();

    $issue = Issue::create([
        'project_id' => $victimProject->id,
        'hash' => 'secret-issue',
        'type' => 'exception',
        'title' => 'Secret',
        'message' => 'confidential',
        'status' => 'open',
        'priority' => 'high',
    ]);

    $this->actingAs($attacker[0])
        ->get(route('issues.show', [...crossTenant($attacker, $victimProject), 'issue' => $issue->id]))
        ->assertNotFound();
});

test('a member cannot overwrite another teams integration secrets', function () {
    $attacker = tenant();
    [, , $victimProject] = tenant();

    $integration = Integration::create([
        'project_id' => $victimProject->id,
        'name' => 'Ops Slack',
        'type' => 'slack',
        'data' => ['webhook_url' => 'https://hooks.slack.com/services/SECRET'],
        'is_enabled' => true,
        'status' => 'healthy',
    ]);

    $this->actingAs($attacker[0])
        ->patch(route('integrations.update', [...crossTenant($attacker, $victimProject), 'integration' => $integration->id]), [
            'name' => 'pwned',
            'is_enabled' => false,
            'data' => ['webhook_url' => 'https://evil.example/steal'],
        ])
        ->assertNotFound();

    // The victim's integration is untouched.
    expect($integration->fresh()->data['webhook_url'])->toBe('https://hooks.slack.com/services/SECRET')
        ->and($integration->fresh()->name)->toBe('Ops Slack');
});

test('a member cannot delete another teams integration', function () {
    $attacker = tenant();
    [, , $victimProject] = tenant();

    $integration = Integration::create([
        'project_id' => $victimProject->id,
        'name' => 'Ops Slack',
        'type' => 'slack',
        'data' => ['webhook_url' => 'https://hooks.slack.com/x'],
        'is_enabled' => true,
        'status' => 'healthy',
    ]);

    $this->actingAs($attacker[0])
        ->delete(route('integrations.destroy', [...crossTenant($attacker, $victimProject), 'integration' => $integration->id]))
        ->assertNotFound();

    expect($integration->fresh())->not->toBeNull();
});

test('a member cannot delete another teams alert rule or threshold', function () {
    $attacker = tenant();
    [, , $victimProject] = tenant();

    $rule = AlertRule::create([
        'project_id' => $victimProject->id,
        'name' => 'Downtime',
        'event_type' => 'uptime_down',
        'settings' => [],
        'is_enabled' => true,
    ]);

    $threshold = Threshold::create([
        'project_id' => $victimProject->id,
        'type' => 'route',
        'key' => '/checkout',
        'value' => 500,
        'is_enabled' => true,
    ]);

    $this->actingAs($attacker[0])
        ->delete(route('alerts.destroy', [...crossTenant($attacker, $victimProject), 'rule' => $rule->id]))
        ->assertNotFound();

    $this->actingAs($attacker[0])
        ->delete(route('thresholds.destroy', [...crossTenant($attacker, $victimProject), 'threshold' => $threshold->id]))
        ->assertNotFound();

    expect($rule->fresh())->not->toBeNull()
        ->and($threshold->fresh())->not->toBeNull();
});

test('a member can still reach their own resources', function () {
    [$user, $team, $project] = tenant();

    $issue = Issue::create([
        'project_id' => $project->id,
        'hash' => 'own-issue',
        'type' => 'exception',
        'title' => 'Mine',
        'message' => 'ok',
        'status' => 'open',
        'priority' => 'low',
    ]);

    $this->actingAs($user)
        ->get(route('issues.show', ['current_team' => $team->slug, 'project' => $project->slug, 'issue' => $issue->id]))
        ->assertOk();
});
