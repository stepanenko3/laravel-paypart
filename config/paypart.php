<?php

return [
    'payment_method' => 'liqpay',
    'payment_methods' => [
        'liqpay' => Stepanenko3\PlanPay\Liqpay::class,
        'applepay' => Stepanenko3\PlanPay\ApplePay::class,
    ],

    'liqpay' => [
        'api_url' => env('LIQPAY_API_URL', 'https://www.liqpay.ua/api/'),
        'checkout_url' => env('LIQPAY_CHECKOUT_URL', 'https://www.liqpay.ua/api/3/checkout'),
        'public_key' => env('LIQPAY_PUBLIC_KEY', 'sandbox_i74361511596'),
        'private_key' => env('LIQPAY_PRIVATE_KEY', 'sandbox_A9B9jd6yIt3iQk9ZHnN6ASveFLT8S5Cu6MpYmAXY'),
    ],
    'applepay' => [
        'api_url' => env('LIQPAY_API_URL', 'https://www.liqpay.ua/api/'),
        'checkout_url' => env('LIQPAY_CHECKOUT_URL', 'https://www.liqpay.ua/api/3/checkout'),
        'public_key' => env('LIQPAY_PUBLIC_KEY', 'sandbox_i74361511596'),
        'private_key' => env('LIQPAY_PRIVATE_KEY', 'sandbox_A9B9jd6yIt3iQk9ZHnN6ASveFLT8S5Cu6MpYmAXY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PlanPay Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI path where PlanPay's views, such as the payment
    | verification screen, will be available from. You're free to tweak
    | this path according to your preferences and application design.
    |
    */

    'path' => env('PLANPAY_PATH', 'planpay'),

    /*
    |--------------------------------------------------------------------------
    | PlanPay Model
    |--------------------------------------------------------------------------
    |
    | This is the model in your application that implements the Billable trait
    | provided by PlanPay. It will serve as the primary model you use while
    | interacting with PlanPay related methods, subscriptions, and so on.
    |
    */

    'model' => env('PLANPAY_MODEL', App\User::class),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various world currencies that are currently supported.
    |
    */

    'currency' => env('PLANPAY_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default en locale
    | verify you have the "intl" PHP extension installed on the system.
    |
    */

    'currency_locale' => env('PLANPAY_CURRENCY_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Payment Confirmation Notification
    |--------------------------------------------------------------------------
    |
    | If this setting is enabled, PlanPay will automatically notify customers
    | whose payments require additional verification. You should listen to
    | webhooks in order for this feature to function correctly.
    |
    */

    'payment_notification' => env('PLANPAY_PAYMENT_NOTIFICATION'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Paper Size
    |--------------------------------------------------------------------------
    |
    | This option is the default paper size for all invoices generated using
    | PlanPay. You are free to customize this settings based on the usual
    | paper size used by the customers using your Laravel applications.
    |
    | Supported sizes: 'letter', 'legal', 'A4'
    |
    */

    'paper' => env('PLANPAY_PAPER', 'letter'),
];
