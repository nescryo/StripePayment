<?php

namespace StripePayment\Panel\ScheduledConference\Pages;

use App\Models\Payment;
use Filament\Pages\Page;

class StripePage extends Page
{
    protected static string $view = 'StripePayment::panel.scheduledConference.pages.stripe';

    protected static bool $shouldRegisterNavigation = false;

    public function __invoke()
    {
        //
    }

    public function handlePayment(Payment $paymentQueue)
    {
        //
    }

    public function completePayment(Payment $paymentQueue)
    {
        //
    }
}
