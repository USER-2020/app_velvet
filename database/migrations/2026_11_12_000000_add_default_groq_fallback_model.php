<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $setting = SystemSetting::query()->where('key', 'groq_assistant')->first();

        if (! $setting) {
            return;
        }

        $value = is_array($setting->value) ? $setting->value : [];
        $models = array_values(array_unique(array_filter(array_map('trim', [
            ...((array) ($value['models'] ?? [])),
            'openai/gpt-oss-20b',
        ]))));

        $setting->update(['value' => [...$value, 'models' => $models]]);
    }

    public function down(): void
    {
        $setting = SystemSetting::query()->where('key', 'groq_assistant')->first();

        if (! $setting) {
            return;
        }

        $value = is_array($setting->value) ? $setting->value : [];
        $models = array_values(array_filter((array) ($value['models'] ?? []), fn (string $model) => $model !== 'openai/gpt-oss-20b'));

        $setting->update(['value' => [...$value, 'models' => $models]]);
    }
};
