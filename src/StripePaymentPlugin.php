<?php

namespace StripePayment;

use App\Classes\Plugin;
use App\Facades\Hook;
use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use Awcodes\Shout\Components\ShoutEntry;
use Filament\Actions\Action;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Panel;
use StripePayment\Panel\ScheduledConference\Livewire\StripeSetting;
use StripePayment\Panel\ScheduledConference\Pages\StripePage;

class StripePaymentPlugin extends Plugin
{
    public function boot()
    {
        if (! app()->getCurrentScheduledConference()) {
            return;
        }

        if ($this->isProperlySetup()) {
            Hook::add('PaymentManager::getPaymentMethodActions', function ($hookName, &$actions) {
                $actions['stripe'] = Action::make('stripe')
                    ->label('Stripe Payment')
                    ->url(fn ($record) => route(StripePage::getRouteName('scheduledConference'), ['id' => $record->getKey()]));

                return false;
            });

            Hook::add('PaymentManager::getPaymentMethodInfolist', function ($hookName, &$schemas) {
                $schemas[] = Section::make('Stripe Payment')
                    ->visible(fn ($record) => $record->payment_method == 'stripe')
                    ->description('')
                    ->schema([
                        ShoutEntry::make('information')
                            ->content('Detailed financial information is securely processed on Stripe')
                            ->type('info'),
                        TextEntry::make('stripe_session_id')
                            ->label('Session ID')
                            ->extraAttributes(['class' => 'break-all'])
                            ->getStateUsing(fn ($record) => $record->getMeta('stripe_session_id')),
                        TextEntry::make('stripe_payment_intent')
                            ->label('Payment Intent')
                            ->extraAttributes(['class' => 'break-all'])
                            ->visible(fn () => auth()->user()?->can('update', app()->getCurrentScheduledConference()))
                            ->getStateUsing(fn ($record) => $record->getMeta('stripe_payment_intent')),
                    ]);

                return false;
            });
        }
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
        return (bool) $this->getSetting('test_mode', false);
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
