<?php

use Illuminate\Support\Facades\Cache;

if (!function_exists('theme_settings')) {
    function theme_settings($key = null, $default = null)
    {
        $theme = app('theme')->getActive();

        $settings = Cache::remember("theme_settings_{$theme}", 3600, function () use ($theme) {
            $configPath = public_path("themes/{$theme}/config/settings_data.json");
            if (!file_exists($configPath)) {
                return [];
            }
            return json_decode(file_get_contents($configPath), true) ?? [];
        });

        if ($key !== null) {
            return $settings[$key] ?? $default;
        }

        return $settings;
    }
}