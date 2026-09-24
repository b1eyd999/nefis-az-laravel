<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // Product images come from a Yandex Disk share that refuses
            // requests carrying a referer from another site.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="referrer" content="no-referrer">',
            )
            // Filament keeps the sidebar open by default, so on a phone every
            // first visit opened on the menu covering the page. On a narrow
            // screen each page now starts with it closed (☰ opens it).
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<script>try{if(matchMedia("(max-width: 1023px)").matches)localStorage.setItem("isOpen","false")}catch(e){}</script>',
            )
            // On a phone Filament stacks the month's four numbers one under
            // another, so the month took a screen and a half; two to a row.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>@media (max-width:767px){'
                    . '.fi-wi-stats-overview-stats-ctn{grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}'
                    . '.fi-wi-stats-overview-stats-ctn .fi-wi-stats-overview-stat{padding:.85rem}'
                    . '.fi-wi-stats-overview-stats-ctn .fi-wi-stats-overview-stat-value{font-size:1.5rem}'
                    . '.fi-wi-stats-overview-stats-ctn .fi-wi-stats-overview-stat-label,'
                    . '.fi-wi-stats-overview-stats-ctn .fi-wi-stats-overview-stat-description{font-size:.75rem}'
                    . '}</style>',
            )
            // A way back to the shop: a button in the top bar and the same
            // line in the account menu, because the panel offers none.
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.back-to-site')->render(),
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label('Sayta qayıt')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url('/'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            // Only the shop's own widgets: Filament's "Welcome" card filled a
            // whole phone screen to say the name that is already in the corner.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
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
