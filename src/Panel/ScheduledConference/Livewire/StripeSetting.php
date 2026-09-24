<?php

namespace StripePayment\Panel\ScheduledConference\Livewire;

use App\Facades\Plugin;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Livewire\Component;

class StripeSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $stripePlugin = Plugin::getPlugin('StripePayment');

        $this->form->fill([
            'payment_enabled' => $stripePlugin->getSetting('payment_enabled', false),
            'test_mode' => $stripePlugin->getSetting('test_mode', false),
            'publishable_key' => $stripePlugin->getSetting('publishable_key', ''),
            'secret_key' => $stripePlugin->getSetting('secret_key', ''),
            'publishable_key_test' => $stripePlugin->getSetting('publishable_key_test', ''),
            'secret_key_test' => $stripePlugin->getSetting('secret_key_test', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Toggle::make('payment_enabled')
                            ->label(__('general.enabled')),
                        Checkbox::make('test_mode')
                            ->label('Sandbox')
                            ->reactive()
                            ->extraAttributes([
                                'x-on:change' => 'console.log($wire.formData.test_mode)',
                            ])
                            ->helperText('Enable sandbox mode for testing'),
                        Grid::make(1)
                            ->maxWidth('xl')
                            ->hidden(fn (Get $get) => $get('test_mode'))
                            ->schema([
                                TextInput::make('publishable_key')
                                    ->label('Live Publishable Key'),
                                TextInput::make('secret_key')
                                    ->label('Live Secret Key'),
                            ]),
                        Grid::make(1)
                            ->maxWidth('xl')
                            ->visible(fn (Get $get) => $get('test_mode'))
                            ->schema([
                                TextInput::make('publishable_key_test')
                                    ->label('Sandbox Publishable Key'),
                                TextInput::make('secret_key_test')
                                    ->label('Sandbox Secret Key'),
                            ]),
                    ]),
                Actions::make([
                    Action::make('save_changes')
                        ->label(__('general.save_changes'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();

                            try {
                                $stripePlugin = Plugin::getPlugin('StripePayment');
                                $stripePlugin->updateSetting('payment_enabled', $formData['payment_enabled']);
                                $stripePlugin->updateSetting('test_mode', $formData['test_mode']);
                                if (! $formData['test_mode']) {
                                    $stripePlugin->updateSetting('publishable_key', $formData['publishable_key']);
                                    $stripePlugin->updateSetting('secret_key', $formData['secret_key']);
                                } else {
                                    $stripePlugin->updateSetting('publishable_key_test', $formData['publishable_key_test']);
                                    $stripePlugin->updateSetting('secret_key_test', $formData['secret_key_test']);
                                }
                            } catch (\Throwable $th) {
                                $action->failure();
                                throw $th;
                            }

                            $action->success();
                        })
                        ->authorize('RegistrationSetting:update'),
                ]),
            ])
            ->statePath('formData');
    }

    public function render()
    {
        return view('forms.form');
    }
}
