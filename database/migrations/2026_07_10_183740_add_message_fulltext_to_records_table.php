<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give log search something to stand on.
     *
     * Searching logs ran `payload LIKE '%term%'` over the whole JSON column — a
     * leading-wildcard scan that reads every log record's full payload. This
     * lifts the searchable text into a `message` column and indexes it FULLTEXT,
     * so search becomes an index lookup on MySQL/MariaDB and PostgreSQL.
     *
     * SQLite (used by the test suite) has no FULLTEXT index on ordinary columns,
     * so the index is created only where supported; the read path falls back to
     * LIKE on the same narrow column there.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->string('message', 1000)->nullable()->after('trace_id');
        });

        if ($this->supportsFullText()) {
            Schema::table('records', function (Blueprint $table) {
                $table->fullText('message', 'records_message_fulltext');
            });
        }
    }

    public function down(): void
    {
        if ($this->supportsFullText()) {
            Schema::table('records', function (Blueprint $table) {
                $table->dropFullText('records_message_fulltext');
            });
        }

        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn('message');
        });
    }

    protected function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
