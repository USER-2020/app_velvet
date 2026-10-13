<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function guidedToursEnabled(): bool
    {
        $setting = static::query()->where('key', 'guided_tours')->first();

        return (bool) data_get($setting?->value, 'enabled', true);
    }

    public static function setGuidedToursEnabled(bool $enabled): void
    {
        static::updateOrCreate(
            ['key' => 'guided_tours'],
            ['value' => ['enabled' => $enabled]],
        );
    }
}
