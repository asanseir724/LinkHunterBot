<?php

// تنظیمات اولیه
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// اتصال به اتولودر Composer
require __DIR__ . '/../vendor/autoload.php';

// ایجاد یک شی جدید از Slim\App
$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true,
        'determineRouteBeforeAppMiddleware' => true,
    ]
]);

// تنظیم کنترلرها
$container = $app->getContainer();
$container['HomeController'] = function($c) {
    return new \App\Controllers\HomeController();
};
$container['LinksController'] = function($c) {
    return new \App\Controllers\LinksController();
};
$container['ChannelsController'] = function($c) {
    return new \App\Controllers\ChannelsController();
};
$container['AccountsController'] = function($c) {
    return new \App\Controllers\AccountsController();
};
$container['SettingsController'] = function($c) {
    return new \App\Controllers\SettingsController();
};

// تعریف مسیرها (Routes)

// صفحه اصلی
$app->get('/', function($request, $response) {
    return $this->HomeController->index($request, $response);
});

// مدیریت لینک‌ها
$app->get('/links', function($request, $response) {
    return $this->LinksController->index($request, $response);
});
$app->get('/links/clear', function($request, $response) {
    return $this->LinksController->clearLinks($request, $response);
});
$app->get('/links/export', function($request, $response) {
    return $this->LinksController->exportLinks($request, $response);
});
$app->get('/api/links', function($request, $response) {
    return $this->LinksController->apiLinks($request, $response);
});

// مدیریت کانال‌ها
$app->get('/channels', function($request, $response) {
    return $this->ChannelsController->index($request, $response);
});
$app->post('/channels/add', function($request, $response) {
    return $this->ChannelsController->addChannel($request, $response);
});
$app->get('/channels/remove/{channel}', function($request, $response, $args) {
    return $this->ChannelsController->removeChannel($request, $response, $args['channel']);
});
$app->post('/channels/update-category', function($request, $response) {
    return $this->ChannelsController->updateCategory($request, $response);
});

// مدیریت حساب‌های کاربری
$app->get('/accounts', function($request, $response) {
    return $this->AccountsController->index($request, $response);
});
$app->post('/accounts/add', function($request, $response) {
    return $this->AccountsController->addAccount($request, $response);
});
$app->get('/accounts/remove/{phone}', function($request, $response, $args) {
    return $this->AccountsController->removeAccount($request, $response, $args['phone']);
});
$app->get('/accounts/connect/{phone}', function($request, $response, $args) {
    return $this->AccountsController->connectAccount($request, $response, $args['phone']);
});
$app->get('/accounts/disconnect/{phone}', function($request, $response, $args) {
    return $this->AccountsController->disconnectAccount($request, $response, $args['phone']);
});
$app->post('/accounts/verify-code', function($request, $response) {
    return $this->AccountsController->verifyCode($request, $response);
});
$app->post('/accounts/verify-2fa', function($request, $response) {
    return $this->AccountsController->verify2FA($request, $response);
});
$app->get('/accounts/check-links', function($request, $response) {
    return $this->AccountsController->checkAccountsForLinks($request, $response);
});
$app->get('/telegram-desktop', function($request, $response) {
    return $this->AccountsController->telegramDesktop($request, $response);
});
$app->post('/telegram-desktop/send-message', function($request, $response) {
    return $this->AccountsController->sendMessage($request, $response);
});

// تنظیمات
$app->get('/settings', function($request, $response) {
    return $this->SettingsController->index($request, $response);
});
$app->post('/settings/update', function($request, $response) {
    return $this->SettingsController->updateSettings($request, $response);
});
$app->post('/settings/update-token', function($request, $response) {
    return $this->SettingsController->updateToken($request, $response);
});
$app->post('/settings/update-check-interval', function($request, $response) {
    return $this->SettingsController->updateCheckInterval($request, $response);
});
$app->get('/settings/check-now', function($request, $response) {
    return $this->SettingsController->checkNow($request, $response);
});
$app->post('/settings/avalai', function($request, $response) {
    return $this->SettingsController->updateAvalaiSettings($request, $response);
});

// اجرای اپلیکیشن
$app->run();