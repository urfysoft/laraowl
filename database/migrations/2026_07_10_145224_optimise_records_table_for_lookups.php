<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->string('user_key', 64)->nullable()->after('fingerprint');
            $table->string('ip', 45)->nullable()->after('user_key');
        });

        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('records_type_index');
            $table->dropIndex('records_created_at_index');
            $table->dropIndex('records_fingerprint_index');

            $table->index(['project_id', 'fingerprint', 'created_at'], 'records_project_fingerprint_created_idx');

            $table->index(['project_id', 'user_key', 'created_at'], 'records_project_user_created_idx');

            $table->index(['project_id', 'type', 'created_at', 'ip'], 'records_project_type_created_ip_idx');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex('records_project_type_created_ip_idx');
            $table->dropIndex('records_project_user_created_idx');
            $table->dropIndex('records_project_fingerprint_created_idx');

            $table->index('type');
            $table->index('created_at');
            $table->index('fingerprint');
        });

        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn(['user_key', 'ip']);
        });
    }
};
