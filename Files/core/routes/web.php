<?php

use Illuminate\Support\Facades\Route;

Route::get('/clear', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
});

Route::get('cron', 'CronController@cron')->name('cron');

// Health check endpoints (publicly accessible for monitoring)
Route::get('/health', 'HealthController@check')->name('health.check');
Route::get('/health/metrics', 'HealthController@metrics')->name('health.metrics');

// User Support Ticket
Route::controller('TicketController')->prefix('ticket')->middleware(['throttle:3,1'])->name('ticket.')->group(function () {
    Route::get('/', 'supportTicket')->name('index');
    Route::get('new', 'openSupportTicket')->name('open');
    Route::post('create', 'storeSupportTicket')->name('store');
    Route::get('view/{ticket}', 'viewTicket')->name('view');
    Route::post('reply/{id}', 'replyTicket')->name('reply');
    Route::post('close/{id}', 'closeTicket')->name('close');
    Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
});

Route::controller('SiteController')->middleware(['throttle:5,1'])->group(function () {
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'contactSubmit');
    Route::get('/change/{lang?}', 'changeLanguage')->name('lang');

    Route::get('cookie-policy', 'cookiePolicy')->name('cookie.policy');

    Route::get('/cookie/accept', 'cookieAccept')->name('cookie.accept');

    Route::get('/products/{catId?}', 'products')->name('products');
    Route::get('/product/{id}/{slug}', 'productDetails')->name('product.details');

    Route::get('/blog', 'blog')->name('blog');
    Route::get('blog/{slug}', 'blogDetails')->name('blog.details');
    Route::get('faq', 'faq')->name('faq');

    Route::post('/check/referral', 'checkUsername')->name('check.referral');
    Route::post('/get/user/position', 'userPosition')->name('get.user.position');

    Route::get('policy/{slug}', 'policyPages')->name('policy.pages');

    Route::get('placeholder-image/{size}', 'placeholderImage')->withoutMiddleware('maintenance')->name('placeholder.image');
    Route::get('maintenance-mode', 'maintenance')->withoutMiddleware('maintenance')->name('maintenance');

    Route::get('/{slug}', 'pages')->name('pages');
    Route::get('/', 'index')->name('home');
});
