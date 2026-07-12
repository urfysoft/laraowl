<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_ip_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamp('bucket');
            $table->string('ip', 45);
            $table->unsignedBigInteger('count')->default(0);

            $table->unique(['project_id', 'type', 'bucket', 'ip'], 'record_ip_buckets_unique');
            $table->index(['project_id', 'type', 'bucket'], 'record_ip_buckets_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_ip_buckets');
    }
};
