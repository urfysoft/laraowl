<?php

namespace App\Concerns;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

trait PrunesByRollupRetention
{
    public function prunableByRollupRetention(): Builder
    {
        $projects = Project::query()
            ->where('rollup_retention_days', '>', 0)
            ->get(['id', 'rollup_retention_days']);

        if ($projects->isEmpty()) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where(function (Builder $query) use ($projects) {
            foreach ($projects as $project) {
                $query->orWhere(function (Builder $builder) use ($project) {
                    $builder->where('project_id', $project->id)
                        ->where('bucket', '<', now()->subDays($project->rollup_retention_days));
                });
            }
        });
    }
}
