<?php

namespace StripePayment;

use App\Classes\Plugin;
use App\Facades\Hook;
use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use Filament\Infolists\Components\Livewire;
use Filament\Panel;
use StripePayment\Panel\ScheduledConference\Livewire\StripeSetting;
use StripePayment\Panel\ScheduledConference\Pages\StripePage;

class StripePaymentPlugin extends Plugin
{
    public function boot()
    {
        //
    }

    public function onPanel(Panel $panel): void
    {
        if ($panel->getId() !== 'scheduledConference') {
            return;
        }

        $panel->discoverLivewireComponents(
            in: $this->pluginPath . '/src/Panel/ScheduledConference/Livewire',
            for: 'StripePayment\\Panel\\ScheduledConference\\Livewire'
        );

        $panel->pages([
            StripePage::class,
        ]);

        Hook::add('Payments::PaymentMethodTabs', function ($hookName, &$tabs) {
            $tabs[] = InfolistsVerticalTabs\Tab::make('stripe')
                ->label('Stripe')
                ->icon('heroicon-o-credit-card')
                ->schema([
                    Livewire::make(StripeSetting::class),
                ]);
        });
    }

    public function isProperlySetup(): bool
    {
        return $this->getSetting('payment_enabled', false) && $this->getPublishableKey() && $this->getSecretKey();
    }

    public function isTestMode(): bool
    {
        return $this->getSetting('test_mode', false);
    }

    public function getPublishableKey(): ?string
    {
        return $this->isTestMode() ? $this->getSetting('publishable_key_test') : $this->getSetting('publishable_key');
    }

    public function getSecretKey(): ?string
    {
        return $this->isTestMode() ? $this->getSetting('secret_key_test') : $this->getSetting('secret_key');
    }
}
