<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use App\Controllers\HomeController;
use App\Controllers\ChannelsController;
use App\Controllers\LinksController;
use App\Controllers\AccountsController;
use App\Controllers\SettingsController;
use App\Services\LinkManager;
use App\Services\TelegramBot;
use App\Services\AvalaiApi;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Create Slim app
$app = AppFactory::create();

// Add middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Add view renderer
$renderer = new PhpRenderer(__DIR__ . '/../templates');
$app->getContainer()['renderer'] = $renderer;

// Initialize services
$linkManager = new LinkManager();
$telegramBot = new TelegramBot($linkManager);
$avalaiApi = new AvalaiApi();

// Register controllers
$homeController = new HomeController($renderer, $linkManager);
$channelsController = new ChannelsController($renderer, $linkManager);
$linksController = new LinksController($renderer, $linkManager);
$accountsController = new AccountsController($renderer, $linkManager, $telegramBot);
$settingsController = new SettingsController($renderer, $linkManager, $avalaiApi);

// Define routes
// Home
$app->get('/', [$homeController, 'index']);

// Channels
$app->get('/channels', [$channelsController, 'index']);
$app->post('/channels/add', [$channelsController, 'add']);
$app->get('/channels/remove/{channel}', [$channelsController, 'remove']);

// Links
$app->get('/links', [$linksController, 'index']);
$app->get('/links/clear', [$linksController, 'clear']);
$app->get('/links/export/{format}', [$linksController, 'export']);
$app->get('/api/links', [$linksController, 'apiLinks']);

// Accounts
$app->get('/accounts', [$accountsController, 'index']);
$app->post('/accounts/add', [$accountsController, 'add']);
$app->get('/accounts/remove/{phone}', [$accountsController, 'remove']);
$app->get('/accounts/connect/{phone}', [$accountsController, 'connect']);
$app->get('/accounts/disconnect/{phone}', [$accountsController, 'disconnect']);
$app->post('/accounts/verify-code', [$accountsController, 'verifyCode']);
$app->post('/accounts/verify-2fa', [$accountsController, 'verify2fa']);
$app->get('/accounts/check-links', [$accountsController, 'checkLinks']);
$app->get('/private-messages', [$accountsController, 'privateMessages']);
$app->get('/telegram-desktop', [$accountsController, 'telegramDesktop']);
$app->get('/accounts/clear-chat-history', [$accountsController, 'clearChatHistory']);

// Settings
$app->get('/settings', [$settingsController, 'index']);
$app->post('/settings/update', [$settingsController, 'update']);
$app->get('/check-now', [$settingsController, 'checkNow']);
$app->get('/avalai-settings', [$settingsController, 'avalaiSettings']);
$app->post('/avalai-settings/update', [$settingsController, 'updateAvalaiSettings']);

// Run app
$app->run();