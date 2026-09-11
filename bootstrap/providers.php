<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$needsAdminPanel = PHP_SAPI === 'cli'
    || str_starts_with($requestPath, '/admin')
    || str_starts_with($requestPath, '/livewire')
    || str_starts_with($requestPath, '/filament');

if ($needsAdminPanel) {
    $providers[] = App\Providers\Filament\AdminPanelProvider::class;
}

return $providers;
