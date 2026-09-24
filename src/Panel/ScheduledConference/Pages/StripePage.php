<?php

namespace StripePayment\Panel\ScheduledConference\Pages;

use App\Facades\Plugin;
use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripePage extends Page
{
    protected static string $view = 'StripePayment::panel.scheduledConference.pages.stripe';

    protected static bool $shouldRegisterNavigation = false;

    public function __invoke()
    {
        $request = app('request');
        $id = $request->input('id');

        abort_if(! $id, 404);

        $paymentQueue = Payment::query()->where('id', $id)->first();

        abort_if(! $paymentQueue, 404);

        // Security Guard: Check user ownership or conference editor privileges
        abort_if(
            $paymentQueue->user_id !== auth()->id() && ! (auth()->check() && auth()->user()->can('update', app()->getCurrentScheduledConference())),
            403,
            'Unauthorized access to payment queue'
        );

        abort_if($paymentQueue->isExpired(), 403, 'Payment Queue expired');

        // Handle user cancellation gracefully
        if ($request->input('cancelled')) {
            Notification::make()
                ->title('Payment Cancelled')
                ->warning()
                ->send();

            return redirect()->to(PaymentDetail::getUrl(['record' => $paymentQueue]));
        }

        // Handle successful checkout return
        if ($request->input('session_id')) {
            return $this->completePayment($paymentQueue);
        }

        // Initial checkout initiation
        return $this->handlePayment($paymentQueue);
    }

    public function handlePayment(Payment $paymentQueue)
    {
        $stripePlugin = Plugin::getPlugin('StripePayment');
        Stripe::setApiKey($stripePlugin->getSecretKey());

        $currency = strtolower($paymentQueue->currency ?? 'usd');
        $amountInCents = $this->formatAmountForStripe((float) $paymentQueue->amount, $currency);

        $returnRoute = static::getRouteName('scheduledConference');

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $paymentQueue->getMeta('title') ?? ('Payment #' . $paymentQueue->id),
                        ],
                        'unit_amount' => $amountInCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => str_replace(
                    '%7BCHECKOUT_SESSION_ID%7D',
                    '{CHECKOUT_SESSION_ID}',
                    route($returnRoute, [
                        'id' => $paymentQueue->id,
                        'session_id' => '{CHECKOUT_SESSION_ID}',
                    ])
                ),
                'cancel_url' => route($returnRoute, [
                    'id' => $paymentQueue->id,
                    'cancelled' => 1,
                ]),
                'client_reference_id' => (string) $paymentQueue->id,
                'customer_email' => auth()->user()?->email,
            ]);

            return redirect($session->url);
        } catch (\Throwable $th) {
            Log::error('Stripe Session creation error: ' . $th->getMessage());
            abort(403, 'Failed to initialize Stripe payment session: ' . $th->getMessage());
        }
    }

    public function completePayment(Payment $paymentQueue)
    {
        try {
            $request = app('request');
            $stripePlugin = Plugin::getPlugin('StripePayment');
            Stripe::setApiKey($stripePlugin->getSecretKey());

            $sessionId = $request->input('session_id');
            $session = Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                Log::warning('Stripe payment status not paid', ['status' => $session->payment_status]);
                abort(403, 'Payment is not completed or still processing.');
            }

            $currency = strtolower($paymentQueue->currency ?? 'usd');
            $expectedAmount = $this->formatAmountForStripe((float) $paymentQueue->amount, $currency);

            if (
                (int) $session->amount_total !== $expectedAmount 
                || strtolower($session->currency) !== $currency
            ) {
                $message = "Amounts mismatch ({$session->amount_total} {$session->currency} vs {$expectedAmount} {$currency})";
                Log::error('Stripe amount mismatch: ' . $message);
                abort(403, 'Payment amount mismatch detected.');
            }

            $paymentManager = PaymentManager::get();
            $paymentManager->fulfillQueued($paymentQueue, 'stripe', auth()?->id());

            $paymentQueue->setMeta('stripe_session_id', $session->id);
            $paymentQueue->setMeta('stripe_payment_intent', $session->payment_intent);

            Notification::make()
                ->title('Payment Success')
                ->success()
                ->send();

            return redirect()->to(PaymentDetail::getUrl(['record' => $paymentQueue]));
        } catch (\Throwable $th) {
            Log::error('Stripe completion error: ' . $th->getMessage());
            abort(403, 'An error occurred while completing payment verification: ' . $th->getMessage());
        }
    }

    protected function formatAmountForStripe(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
            'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
