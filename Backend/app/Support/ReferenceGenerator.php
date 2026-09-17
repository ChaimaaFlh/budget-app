<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReferenceGenerator
{
    /**
     * Génère une référence interne lisible et vérifie son unicité, y compris
     * parmi les enregistrements supprimés logiquement.
     *
     * @param class-string<Model> $modelClass
     */
    public static function make(string $modelClass, string $column, string $prefix): string
    {
        $year = now()->year;
        $pattern = '/^' . preg_quote("{$prefix}-{$year}-", '/') . '(\\d+)$/';
        $lastNumber = $modelClass::withTrashed()
            ->where($column, 'like', "{$prefix}-{$year}-%")
            ->pluck($column)
            ->map(function (string $reference) use ($pattern): int {
                preg_match($pattern, $reference, $matches);
                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        return sprintf('%s-%s-%04d', $prefix, $year, $lastNumber + 1);
    }

    public static function isAutomatic(?string $reference, string $prefix): bool
    {
        return $reference !== null
            && preg_match('/^' . preg_quote($prefix, '/') . '-\\d{4}-\\d{4}$/', $reference) === 1;
    }

    /**
     * Creates a model with an automatically generated reference. The database
     * unique index remains the source of truth; collisions are retried.
     *
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $attributes
     */
    public static function create(string $modelClass, string $column, string $prefix, array $attributes): Model
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $attributes[$column] = self::make($modelClass, $column, $prefix);

            try {
                return DB::transaction(fn () => $modelClass::create($attributes));
            } catch (QueryException $exception) {
                if (!in_array($exception->getCode(), ['23000', '23505'], true) || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Impossible de générer une référence unique.');
    }
}
