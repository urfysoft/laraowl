<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    protected $fillable = [
        'project_id',
        'hash',
        'type',
        'title',
        'message',
        'status',
        'priority',
        'assigned_to',
        'occurrences_count',
        'users_count',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get the absolute URL to this issue.
     *
     * Built against the configured app URL so it stays correct when generated
     * outside of an HTTP request, such as from a queued job or scheduled command.
     */
    public function url(): string
    {
        $this->loadMissing('project.team');

        return rtrim(config('app.url'), '/').route('issues.show', [
            'current_team' => $this->project->team,
            'project' => $this->project,
            'issue' => $this,
        ], absolute: false);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(IssueActivity::class);
    }
}
