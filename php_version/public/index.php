<?php

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use App\Services\LinkManager;
use App\Services\AvalaiAPI;
use App\Services\AccountManager;
use App\Controllers\HomeController;
use App\Controllers\LinksController;
use App\Controllers\ChannelsController;
use App\Controllers\AccountsController;
use App\Controllers\SettingsController;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Start session
session_start();

// Create logger
$log = new Logger('app');
$handler = new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG);
$handler->setFormatter(new JsonFormatter());
$log->pushHandler($handler);

// Create Slim app
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

// Add routing middleware
$app->addRoutingMiddleware();

// Set base path for templates
$templatesPath = __DIR__ . '/../templates';
$renderer = new PhpRenderer($templatesPath);

// Create services
$linkManager = new LinkManager();
$avalaiAPI = new AvalaiAPI($_ENV['AVALAI_API_KEY'] ?? null);
$accountManager = new AccountManager(__DIR__ . '/../storage/accounts_data.json', $log);

// Create controllers
$homeController = new HomeController($linkManager, $renderer, $log);
$linksController = new LinksController($linkManager, $renderer, $log);
$channelsController = new ChannelsController($linkManager, $renderer, $log);
$accountsController = new AccountsController($accountManager, $renderer, $log);
$settingsController = new SettingsController($linkManager, $avalaiAPI, $renderer, $log);

// Define routes

// Home routes
$app->get('/', [$homeController, 'index']);

// Links routes
$app->get('/links', [$linksController, 'index']);
$app->get('/api/links', [$linksController, 'apiLinks']);
$app->get('/links/clear', [$linksController, 'clearLinks']);
$app->get('/links/export/all', [$linksController, 'exportAllLinks']);
$app->get('/links/export/new', [$linksController, 'exportNewLinks']);

// Channels routes
$app->get('/channels', [$channelsController, 'index']);
$app->post('/channels/add', [$channelsController, 'addChannel']);
$app->get('/channels/remove/{channel}', [$channelsController, 'removeChannel']);
$app->get('/channels/check-now', [$channelsController, 'checkNow']);

// Accounts routes
$app->get('/accounts', [$accountsController, 'index']);
$app->post('/accounts/add', [$accountsController, 'addAccount']);
$app->get('/accounts/verify-code', [$accountsController, 'verifyCodePage']);
$app->post('/accounts/verify-code', [$accountsController, 'verifyCode']);
$app->get('/accounts/verify-2fa', [$accountsController, 'verify2FAPage']);
$app->post('/accounts/verify-2fa', [$accountsController, 'verify2FA']);
$app->get('/accounts/connect/{phone}', [$accountsController, 'connectAccount']);
$app->get('/accounts/disconnect/{phone}', [$accountsController, 'disconnectAccount']);
$app->get('/accounts/remove/{phone}', [$accountsController, 'removeAccount']);
$app->get('/accounts/check-links', [$accountsController, 'checkAccountsForLinks']);
$app->get('/telegram-desktop', [$accountsController, 'telegramDesktop']);
$app->get('/telegram-desktop/add-sample', [$accountsController, 'addSampleMessages']);
$app->get('/telegram-desktop/clear-history[/{chat}]', [$accountsController, 'clearChatHistory']);

// Settings routes
$app->get('/settings', [$settingsController, 'index']);
$app->post('/settings/save', [$settingsController, 'saveSettings']);
$app->post('/settings/avalai', [$settingsController, 'saveAvalaiSettings']);

// Run the app
$app->run();