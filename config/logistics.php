<?php

// Configuración de la ficha logística. Se lee siempre con config('logistics.*'):
// env() devuelve null cuando la configuración está cacheada (php artisan config:cache).
return [
    'frontend_url' => env('LOGISTICS_SHEET_FRONTEND_URL', ''),
    'edit_cutoff_days' => (int) env('LOGISTICS_SHEET_EDIT_CUTOFF_DAYS', 1),
    'test_mode' => filter_var(env('LOGISTICS_SHEET_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),
    'reminders_secret' => env('LOGISTICS_SHEET_REMINDERS_SECRET'),
];
