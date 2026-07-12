<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_user_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamp('bucket');
            $table->string('user_key', 64);
            $table->unsignedBigInteger('count')->default(0);
            $table->unsignedBigInteger('error_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();

            $table->unique(['project_id', 'type', 'bucket', 'user_key'], 'record_user_buckets_unique');
            $table->index(['project_id', 'type', 'bucket'], 'record_user_buckets_lookup_idx');
            $table->index(['project_id', 'user_key', 'bucket'], 'record_user_buckets_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_user_buckets');
    }
};
