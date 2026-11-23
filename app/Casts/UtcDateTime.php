<?php

namespace App\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads and writes database timestamps as UTC without applying the app's
 * business timezone a second time when Eloquent hydrates the model.
 */
class UtcDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, 'UTC');

        return $date->utc()->format($model->getDateFormat());
    }
}
