<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ __('Payment Confirmation') }} - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .btn--applepay {
            margin-bottom: 1rem;
            height: 48px;
            width: 100%;
            border-radius: .5rem;
        }
    </style>
</head>
<body class="font-sans text-gray-600 bg-gray-200 leading-normal p-4 h-full">
    <div id="app" class="h-full md:flex md:justify-center md:items-center">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-lg shadow-xl p-4 sm:py-6 sm:px-10 mb-5">
                @if ($payment->isComplete())
                    <h1 class="text-xl mt-2 mb-4 text-gray-700">
                        {{ __('Payment Successful') }}
                    </h1>

                    <p class="mb-6">{{ __('This payment was already successfully confirmed.') }}</p>
                @elseif ($payment->isCancelled())
                    <h1 class="text-xl mt-2 mb-4 text-gray-700">
                        {{ __('Payment Cancelled') }}
                    </h1>

                    <p class="mb-6">{{ __('This payment was cancelled.') }}</p>
                @else
                    @if ($payment->isError())
                    <p class="flex items-center mb-4 bg-red-100 border border-red-200 px-5 py-2 rounded-lg text-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="flex-shrink-0 w-6 h-6">
                            <path class="fill-current text-red-300" d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20z"/>
                            <path class="fill-current text-red-500" d="M12 18a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm1-5.9c-.13 1.2-1.88 1.2-2 0l-.5-5a1 1 0 0 1 1-1.1h1a1 1 0 0 1 1 1.1l-.5 5z"/>
                        </svg>

                        <span class="ml-3">{{ __('This payment with error.') }}</span>
                    </p>
                    @endif

                    <h1 class="text-xl mt-2 mb-4 text-gray-700">
                        {{ __('Confirm your :amount payment', ['amount' => $payment->amount()]) }}
                    </h1>
                    <div id="payment-elements">
                        @if (isset($methods['liqpay']))
                        {!! $payment->getForm('liqpay') !!}
                        @endif
                    </div>
                @endif

                <a href="{{ $redirect ?? url('/') }}"
                   class="inline-block w-full px-4 py-3 bg-gray-200 hover:bg-gray-300 text-center text-gray-700 rounded-lg">
                    {{ __('Go back') }}
                </a>
            </div>

            <p class="text-center text-gray-500 text-sm">
                © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </div>

    <script>
    // if (window.ApplePaySession && window.ApplePaySession.canMakePayments) {
    //     var payElements = document.getElementById('payment-elements');
    //     var button = document.createElement('button');
    //     button.setAttribute('lang', 'ru');
    //     button.setAttribute('class', 'btn--applepay');

    //     if (window.ApplePaySession.canMakePaymentsWithActiveCard) {
    //         button.setAttribute('style', '-webkit-appearance: -apple-pay-button; -apple-pay-button-type: check-out; -apple-pay-button-style: black');
    //         payElements.appendChild(button);
    //     } else {
    //         button.setAttribute('style', '-webkit-appearance: -apple-pay-button; -apple-pay-button-type: set-up; -apple-pay-button-style: black');
    //         payElements.appendChild(button);
    //     }

        
    // }
    </script>
</body>
</html>
