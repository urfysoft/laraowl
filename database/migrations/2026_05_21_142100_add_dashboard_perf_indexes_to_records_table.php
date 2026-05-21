<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds two indexes that materially speed up the project dashboard once a
     * single project accumulates a few hundred thousand records.
     *
     * Observed on a 2,000-bot internal load test (~700k records in 24h on
     * MariaDB 11): the dashboard time-bucket query (no type filter) and the
     * "top exceptions by fingerprint" query both regressed past the 30s
     * Octane request timeout. The existing composite
     * `(project_id, type, created_at)` only helps when `type` is included
     * in the WHERE clause; aggregations that group by `type` or filter by
     * `fingerprint` were falling back to broader scans.
     *
     * Index choices:
     * - `(project_id, created_at)` — covers `WHERE project_id=? AND
     *   created_at >= ?` with no `type` predicate (used by the per-project
     *   time-series widgets and the records timeline).
     * - `(project_id, type, fingerprint)` — covers `WHERE project_id=? AND
     *   type='exception' GROUP BY fingerprint` and the equivalent path for
     *   `slow-query`/`request` fingerprint aggregation.
     *
     * Both indexes are additive — the existing `(project_id, type,
     * created_at)` index is left in place because it remains the right
     * choice for filtered time queries.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->index(['project_id', 'created_at'], 'records_project_created_idx');
            $table->index(['project_id', 'type', 'fingerprint'], 'records_project_type_fingerprint_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('records_project_type_fingerprint_idx');
            $table->dropIndex('records_project_created_idx');
        });
    }
};
