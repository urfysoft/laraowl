<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->string('trace_id', 64)->nullable()->after('ip');
            $table->index(['project_id', 'trace_id'], 'records_project_trace_idx');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('records_project_trace_idx');
            $table->dropColumn('trace_id');
        });
    }
};
