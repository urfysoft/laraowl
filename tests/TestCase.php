<?php

namespace Tests;

use App\Http\Middleware\CheckOnboarding;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(CheckOnboarding::class);

        $connection = DB::connection();
        if ($connection instanceof SQLiteConnection) {
            $connection->getPdo()->sqliteCreateFunction('JSON_UNQUOTE', function ($value) {
                return is_null($value) ? null : trim($value, '"');
            });

            $connection->getPdo()->sqliteCreateFunction('MD5', function ($value) {
                return is_null($value) ? null : md5($value);
            });

            $connection->getPdo()->sqliteCreateFunction('DATE_FORMAT', function ($value, $format) {
                if (is_null($value)) {
                    return null;
                }
                $date = new \DateTime($value);
                $phpFormat = str_replace(
                    ['%Y', '%m', '%d', '%H', '%i', '%s'],
                    ['Y', 'm', 'd', 'H', 'i', 's'],
                    $format
                );

                return $date->format($phpFormat);
            });
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
