<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\NewsroomQuickActions;
use App\Filament\Widgets\NewsroomStatsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use App\Http\Middleware\SetLocaleToEnglish;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $plugins = [];
        $logPluginClass = 'Saade\\FilamentLaravelLog\\FilamentLaravelLogPlugin';

        if (class_exists($logPluginClass)) {
            $plugins[] = $logPluginClass::make();
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('رابطة الشغيلة')
            ->brandLogo('/website-logo.png')
            ->brandLogoHeight('3rem')
            ->favicon('/logo-fav.png')
            ->colors([
                'primary' => Color::Red,
            ])
            ->assets([
                Css::make('shaghilla-admin-brand', '/css/admin-brand.css')
                    ->html('/css/admin-brand.css'),
            ])
            ->plugins($plugins)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->navigationGroups([
                'News',
                'Media',
                'Contact',
                'Membership',
                'Public Money',
                'Automation',
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                NewsroomStatsOverview::class,
                NewsroomQuickActions::class,
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocaleToEnglish::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
