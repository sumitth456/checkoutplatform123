<?php

namespace App\Modules\Admin\Support;

use Illuminate\Support\Facades\DB;

class DashboardSql
{
    public static function jsonText(string $column, string $path, ?string $alias = null): string
    {
        $expression = match (DB::connection()->getDriverName()) {
            'sqlite' => "json_extract({$column}, '$.{$path}')",
            'pgsql' => "{$column}->>'{$path}'",
            default => "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$path}'))",
        };

        if ($alias) {
            return "{$expression} as {$alias}";
        }

        return $expression;
    }
}
