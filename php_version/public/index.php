<?php
/**
 * نقطه ورودی اصلی برنامه
 * 
 * این فایل به عنوان نقطه ورودی برای برنامه عمل می‌کند و
 * مسیریابی درخواست‌ها را انجام می‌دهد.
 */

// مسیر اصلی پروژه (یک سطح بالاتر از public)
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

// بارگذاری Composer autoloader
require PROJECT_ROOT . '/vendor/autoload.php';

// بارگذاری فایل .env در صورت وجود
if (file_exists(PROJECT_ROOT . '/.env')) {
    $dotenv = new \Dotenv\Dotenv(PROJECT_ROOT);
    $dotenv->load();
}

// تنظیمات خطایابی
if ($_ENV['DEBUG'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// تنظیمات جلسه
session_start([
    'cookie_lifetime' => 86400, // یک روز
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

// ایجاد نمونه Slim App
$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => ($_ENV['DEBUG'] ?? false),
        'addContentLengthHeader' => false,
        'determineRouteBeforeAppMiddleware' => true
    ]
]);

// ثبت container dependencies
$container = $app->getContainer();

// ثبت service providers
$container['linkManager'] = function ($c) {
    return new \App\Services\LinkManager();
};

$container['accountManager'] = function ($c) {
    return new \App\Services\AccountManager();
};

$container['avalaiAPI'] = function ($c) {
    return new \App\Services\AvalaiAPI($_ENV['AVALAI_API_KEY'] ?? null);
};

// مسیریابی
// صفحه اصلی
$app->get('/', \App\Controllers\HomeController::class . ':index');

// مدیریت کانال‌ها
$app->get('/channels', \App\Controllers\ChannelsController::class . ':index');
$app->post('/channels/add', \App\Controllers\ChannelsController::class . ':addChannel');
$app->get('/channels/remove/{channel}', \App\Controllers\ChannelsController::class . ':removeChannel');

// مدیریت لینک‌ها
$app->get('/links', \App\Controllers\LinksController::class . ':index');
$app->get('/links/clear', \App\Controllers\LinksController::class . ':clearLinks');
$app->get('/links/export', \App\Controllers\LinksController::class . ':exportLinks');
$app->get('/links/check-now', \App\Controllers\LinksController::class . ':checkNow');

// مدیریت حساب‌های کاربری تلگرام
$app->get('/accounts', \App\Controllers\AccountsController::class . ':index');
$app->post('/accounts/add', \App\Controllers\AccountsController::class . ':addAccount');
$app->get('/accounts/remove/{phone}', \App\Controllers\AccountsController::class . ':removeAccount');
$app->get('/accounts/connect/{phone}', \App\Controllers\AccountsController::class . ':connectAccount');
$app->get('/accounts/disconnect/{phone}', \App\Controllers\AccountsController::class . ':disconnectAccount');
$app->post('/accounts/verify-code', \App\Controllers\AccountsController::class . ':verifyCode');
$app->post('/accounts/verify-2fa', \App\Controllers\AccountsController::class . ':verify2FA');
$app->get('/accounts/check-links', \App\Controllers\AccountsController::class . ':checkAccountsForLinks');

// مدیریت پیام‌های خصوصی
$app->get('/telegram-desktop', \App\Controllers\AccountsController::class . ':telegramDesktop');
$app->post('/telegram-desktop/send-message', \App\Controllers\AccountsController::class . ':sendMessage');

// تنظیمات
$app->get('/settings', \App\Controllers\SettingsController::class . ':index');
$app->post('/settings/save', \App\Controllers\SettingsController::class . ':saveSettings');
$app->post('/settings/avalai', \App\Controllers\SettingsController::class . ':saveAvalaiSettings');

// API نقطه پایان
$app->get('/api/links', \App\Controllers\APIController::class . ':getLinks');

// اجرای برنامه
$app->run();