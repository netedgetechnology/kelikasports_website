<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting ? ($setting->value ?? $default) : $default;
    }

    public static function write(string $key, mixed $value, string $group = 'general'): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
