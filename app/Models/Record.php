<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Record extends Model
{
    use HasFactory, MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'issue_id',
        'type',
        'payload',
        'fingerprint',
        'user_key',
        'ip',
        'trace_id',
        'message',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * Query Scopes
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeLast24Hours($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    public function scopeForPeriod($query, ?string $period, ?string $from = null, ?string $to = null)
    {
        if ($period === 'custom' && $from && $to) {
            return $query->whereBetween('created_at', [$from, $to]);
        }

        // Floored to the minute so a raw count and the minute-bucketed rollups
        // cover exactly the same window, which lets the rollups supply a
        // paginator's total instead of a COUNT(*) over every record.
        return $query->where('created_at', '>=', static::periodStartsAt($period)->startOfMinute());
    }

    /**
     * Resolve a dashboard period into the instant its window opens.
     *
     * An unrecognised period must never widen the window: returning the query
     * unfiltered turned every such call into a full table scan.
     */
    public static function periodStartsAt(?string $period): CarbonInterface
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '7d' => now()->subDays(7),
            '14d' => now()->subDays(14),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };
    }

    public function scopeFailed($query)
    {
        return $query->where(function ($q) {
            $q->where('payload->status', 'failed')
                ->orWhere('payload->status_code', '>=', 400);
        });
    }

    /**
     * Get the prunable model query.
     */
    public function prunable()
    {
        return static::whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('projects')
                ->whereColumn('projects.id', 'records.project_id')
                ->where('projects.retention_days', '>', 0)
                ->whereRaw('records.created_at < DATE_SUB(NOW(), INTERVAL projects.retention_days DAY)');
        });
    }
}
