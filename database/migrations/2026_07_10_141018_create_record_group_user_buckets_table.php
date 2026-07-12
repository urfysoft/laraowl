<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_group_user_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamp('bucket');
            $table->string('group_key', 64);
            $table->string('user_key', 64);

            $table->unique(['project_id', 'type', 'bucket', 'group_key', 'user_key'], 'record_group_user_buckets_unique');
            $table->index(['project_id', 'type', 'group_key'], 'record_group_user_buckets_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_group_user_buckets');
    }
};
