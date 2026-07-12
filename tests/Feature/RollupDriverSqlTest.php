<?php

use App\Services\RollupWriter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Build a query grammar for a driver without opening a connection.
 *
 * `getQueryGrammar()` instantiates the grammar but never touches a PDO, so this
 * works on a machine that has no MySQL or Postgres server — which is the point:
 * the Postgres branch is exercised even where it cannot be run.
 */
function grammarFor(string $driver)
{
    $name = $driver.'_probe';

    Config::set("database.connections.{$name}", [
        'driver' => $driver,
        'host' => '127.0.0.1',
        'database' => 'probe',
        'username' => 'probe',
        'password' => 'probe',
        'prefix' => '',
    ]);

    return DB::connection($name)->getQueryGrammar();
}

function conflictClauseFor(string $driver, string $table, array $conflict, array $additive, array $max = [], array $min = [], array $overwrite = []): string
{
    $writer = app(RollupWriter::class);
    $method = new ReflectionMethod($writer, 'conflictClause');
    $method->setAccessible(true);

    return $method->invoke($writer, $driver, grammarFor($driver), $table, $conflict, $additive, $max, $min, $overwrite);
}

test('the postgres upsert adds onto the existing row via excluded', function () {
    $sql = conflictClauseFor('pgsql', 'record_rollups', ['project_id', 'type', 'bucket'], ['count'], ['max_duration'], ['min_duration']);

    expect($sql)->toContain('on conflict ("project_id", "type", "bucket") do update set')
        ->and($sql)->toContain('"count" = "record_rollups"."count" + excluded."count"')
        // Postgres greatest()/least() already ignore nulls, so no coalesce.
        ->and($sql)->toContain('"max_duration" = greatest("record_rollups"."max_duration", excluded."max_duration")')
        ->and($sql)->toContain('"min_duration" = least("record_rollups"."min_duration", excluded."min_duration")')
        ->and($sql)->not->toContain('values(')
        ->and($sql)->not->toContain('coalesce');
});

test('the mysql upsert increments and guards the extremes against nulls', function () {
    $sql = conflictClauseFor('mysql', 'record_rollups', ['project_id', 'type', 'bucket'], ['count'], ['max_duration'], ['min_duration']);

    expect($sql)->toContain('on duplicate key update')
        ->and($sql)->toContain('`count` = `count` + values(`count`)')
        // MySQL greatest(x, null) is null, so both sides are coalesced.
        ->and($sql)->toContain('`max_duration` = greatest(coalesce(`max_duration`, values(`max_duration`)), coalesce(values(`max_duration`), `max_duration`))')
        ->and($sql)->not->toContain('excluded.');
});

test('overwrite columns replace rather than add, on both drivers', function () {
    $pg = conflictClauseFor('pgsql', 'record_group_rollups', ['project_id', 'type', 'bucket', 'group_key'], ['count'], [], [], ['label', 'sublabel']);
    $my = conflictClauseFor('mysql', 'record_group_rollups', ['project_id', 'type', 'bucket', 'group_key'], ['count'], [], [], ['label', 'sublabel']);

    expect($pg)->toContain('"label" = excluded."label"')
        ->and($pg)->toContain('on conflict ("project_id", "type", "bucket", "group_key")')
        ->and($my)->toContain('`label` = values(`label`)');
});

test('the conflict target matches each rollup table unique key', function () {
    // Postgres infers the arbiter index from the conflict columns, so they must
    // line up with the unique index each migration declares.
    $cases = [
        'record_rollups' => ['project_id', 'type', 'bucket'],
        'record_group_rollups' => ['project_id', 'type', 'bucket', 'group_key'],
        'record_user_buckets' => ['project_id', 'type', 'bucket', 'user_key'],
        'record_ip_buckets' => ['project_id', 'type', 'bucket', 'ip'],
    ];

    foreach ($cases as $table => $conflict) {
        $sql = conflictClauseFor('pgsql', $table, $conflict, ['count']);
        $target = '('.implode(', ', array_map(fn ($c) => "\"{$c}\"", $conflict)).')';

        expect($sql)->toContain("on conflict {$target} do update set");
    }
});

test('every additive rollup column appears in both drivers upserts', function () {
    foreach (['pgsql', 'mysql'] as $driver) {
        $sql = conflictClauseFor($driver, 'record_rollups', ['project_id', 'type', 'bucket'], RollupWriter::additiveColumns(), ['max_duration'], ['min_duration']);

        foreach (RollupWriter::additiveColumns() as $column) {
            expect($sql)->toContain($column);
        }
    }
});
