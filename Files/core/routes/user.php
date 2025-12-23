<?php

use Illuminate\Support\Facades\Route;

Route::namespace('User\Auth')->name('user.')->middleware('guest')->group(function () {
    Route::controller('LoginController')->group(function () {
        Route::get('/login', 'showLoginForm')->name('login');
        Route::post('/login', 'login')->middleware(['throttle:5,1']);
        Route::get('logout', 'logout')->middleware('auth')->withoutMiddleware('guest')->name('logout');
    });

    Route::controller('RegisterController')->middleware(['guest'])->group(function () {
        Route::get('register', 'showRegistrationForm')->name('register');
        Route::post('register', 'register')->middleware(['throttle:3,1']);
        Route::post('check-user', 'checkUser')
            ->name('checkUser')
            ->withoutMiddleware('guest')
            ->middleware(['throttle:5,1']);
    });

    Route::controller('ForgotPasswordController')->prefix('password')->middleware(['throttle:3,1'])->name('password.')->group(function () {
        Route::get('reset', 'showLinkRequestForm')->name('request');
        Route::post('email', 'sendResetCodeEmail')->name('email');
        Route::get('code-verify', 'codeVerify')->name('code.verify');
        Route::post('verify-code', 'verifyCode')->name('verify.code');
    });

    Route::controller('ResetPasswordController')->middleware(['throttle:5,1'])->group(function () {
        Route::post('password/reset', 'reset')->name('password.update');
        Route::get('password/reset/{token}', 'showResetForm')->name('password.reset');
    });
});

Route::middleware('auth')->name('user.')->group(function () {

    Route::get('user-data', 'User\UserController@userData')->name('data');
    Route::post('user-data-submit', 'User\UserController@userDataSubmit')->name('data.submit');

    //authorization
    Route::middleware('registration.complete')->namespace('User')->controller('AuthorizationController')->group(function () {
        Route::get('authorization', 'authorizeForm')->name('authorization');
        Route::get('resend-verify/{type}', 'sendVerifyCode')->name('send.verify.code');
        Route::post('verify-email', 'emailVerification')->name('verify.email');
        Route::post('verify-mobile', 'mobileVerification')->name('verify.mobile');
        Route::post('verify-g2fa', 'g2faVerification')->name('2fa.verify');
    });

    Route::middleware(['check.status', 'registration.complete'])->group(function () {

        Route::namespace('User')->group(function () {

            Route::controller('UserController')->group(function () {
                Route::get('dashboard', 'home')->name('home');
                Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');

                //2FA
                Route::get('twofactor', 'show2faForm')->name('twofactor');
                Route::post('twofactor/enable', 'create2fa')->name('twofactor.enable');
                Route::post('twofactor/disable', 'disable2fa')->name('twofactor.disable');

                //KYC
                Route::get('kyc-form', 'kycForm')->name('kyc.form');
                Route::get('kyc-data', 'kycData')->name('kyc.data');
                Route::post('kyc-submit', 'kycSubmit')->name('kyc.submit');

                //Report
                Route::any('deposit/history', 'depositHistory')->name('deposit.history');
                Route::get('transactions', 'transactions')->name('transactions');

                //Balance Transfer
                Route::get('transfer', 'indexTransfer')->name('balance.transfer');
                Route::post('transfer', 'balanceTransfer');

                Route::post('search-user', 'searchUser')->name('search.user');


                Route::post('add-device-token', 'addDeviceToken')->name('add.device.token');

                //Orders
                Route::get('orders', 'orders')->name('orders');

                // Bonus & settlements
                Route::get('bonus-center', 'bonusCenter')->name('bonus.center');
                Route::get('weekly-settlements', 'weeklySettlements')->name('weekly.settlements');
                Route::get('weekly-settlements/{week}', 'weeklySettlementShow')->name('weekly.settlements.show');
                Route::get('quarterly-dividends', 'quarterlyDividends')->name('quarterly.dividends');
                Route::get('points', 'pointsCenter')->name('points.center');
                Route::post('points/check-in', 'dailyCheckIn')->name('points.checkin');

                //purchase
                Route::post('purchase', 'purchase')->name('purchase');
            });

            //Profile setting
            Route::controller('ProfileController')->group(function () {
                Route::get('profile-setting', 'profile')->name('profile.setting');
                Route::post('profile-setting', 'submitProfile');
                Route::get('change-password', 'changePassword')->name('change.password');
                Route::post('change-password', 'submitPassword');
            });


            // Withdraw
            Route::controller('WithdrawController')->prefix('withdraw')->name('withdraw')->group(function () {
                Route::middleware('kyc')->group(function () {
                    Route::get('/', 'withdrawMoney');
                    Route::post('/', 'withdrawStore')->name('.money');
                    Route::get('preview', 'withdrawPreview')->name('.preview');
                    Route::post('preview', 'withdrawSubmit')->name('.submit');
                });
                Route::get('history', 'withdrawLog')->name('.history');
            });

            //Plan
            Route::controller('PlanController')->prefix('plan')->name('plan.')->group(function () {
                Route::get('/', 'planIndex')->name('index');
                Route::post('/', 'planStore')->name('purchase');
            });
            Route::controller('PlanController')->group(function () {
                Route::get('bv-log', 'bvlog')->name('bv.log');
                Route::get('my-tree', 'myTree')->name('my.tree');
                Route::get('referrals', 'myRefLog')->name('my.ref');
                Route::get('binary-summery', 'binarySummery')->name('binary.summery');
                Route::get('tree/{user}', 'otherTree')->name('other.tree');
                Route::get('tree/search', 'otherTree')->name('other.tree.search');
            });

            // 七宝进阶
            Route::controller('SevenTreasuresController')->prefix('seven-treasures')->name('seven.treasures.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('my-rank', 'getMyRankInfo')->name('my.rank');
                Route::post('check-promotion', 'checkPromotionEligibility')->name('check.promotion');
                Route::get('all-ranks', 'getAllRanks')->name('all.ranks');
                Route::get('downlines', 'getMyDownlines')->name('downlines');
                Route::get('estimated-rank', 'getEstimatedRank')->name('estimated.rank');
            });
        });

        // Payment
        Route::prefix('deposit')->name('deposit.')->controller('Gateway\PaymentController')->group(function () {
            Route::any('/', 'deposit')->name('index');
            Route::post('insert', 'depositInsert')->name('insert');
            Route::get('confirm', 'depositConfirm')->name('confirm');
            Route::get('manual', 'manualDepositConfirm')->name('manual.confirm');
            Route::post('manual', 'manualDepositUpdate')->name('manual.update');
        });
    });
});
