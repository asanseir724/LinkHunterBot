<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use App\Services\LinkManager;
use App\Services\TelegramBot;
use App\Services\AccountManager;
use App\Services\AvalaiApi;

class AccountsController
{
    private PhpRenderer $renderer;
    private LinkManager $linkManager;
    private TelegramBot $telegramBot;
    private AccountManager $accountManager;
    private ?AvalaiApi $avalaiApi;
    
    public function __construct(PhpRenderer $renderer, LinkManager $linkManager, TelegramBot $telegramBot)
    {
        $this->renderer = $renderer;
        $this->linkManager = $linkManager;
        $this->telegramBot = $telegramBot;
        $this->accountManager = new AccountManager($linkManager);
        $this->avalaiApi = new AvalaiApi();
    }
    
    /**
     * View accounts page
     */
    public function index(Request $request, Response $response): Response
    {
        $accounts = $this->accountManager->getAccounts();
        
        $data = [
            'accounts' => $accounts
        ];
        
        return $this->renderer->render($response, 'accounts.php', $data);
    }
    
    /**
     * Add an account
     */
    public function add(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        $phone = $params['phone'] ?? '';
        
        if (!empty($phone)) {
            $result = $this->accountManager->addAccount($phone);
            
            if ($result['success']) {
                // Redirect to connect account page
                return $response->withHeader('Location', "/accounts/connect/{$phone}")->withStatus(302);
            }
        }
        
        // Redirect back to accounts page
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Remove an account
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        $phone = $args['phone'] ?? '';
        
        if (!empty($phone)) {
            $this->accountManager->removeAccount($phone);
        }
        
        // Redirect back to accounts page
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Connect an account
     */
    public function connect(Request $request, Response $response, array $args): Response
    {
        $phone = $args['phone'] ?? '';
        
        if (empty($phone)) {
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        $result = $this->accountManager->connectAccount($phone);
        
        $data = [
            'phone' => $phone,
            'result' => $result
        ];
        
        return $this->renderer->render($response, 'connect_account.php', $data);
    }
    
    /**
     * Disconnect an account
     */
    public function disconnect(Request $request, Response $response, array $args): Response
    {
        $phone = $args['phone'] ?? '';
        
        if (!empty($phone)) {
            $this->accountManager->disconnectAccount($phone);
        }
        
        // Redirect back to accounts page
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Verify code for an account
     */
    public function verifyCode(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        $phone = $params['phone'] ?? '';
        $code = $params['code'] ?? '';
        $phoneCodeHash = $params['phone_code_hash'] ?? '';
        
        if (!empty($phone) && !empty($code) && !empty($phoneCodeHash)) {
            $result = $this->accountManager->verifyCode($phone, $code, $phoneCodeHash);
            
            if ($result['success']) {
                if ($result['status'] === '2fa_required') {
                    // Render 2FA page
                    $data = [
                        'phone' => $phone,
                        'result' => $result
                    ];
                    
                    return $this->renderer->render($response, 'verify_2fa.php', $data);
                } else if ($result['status'] === 'registration_required') {
                    // Render registration page
                    $data = [
                        'phone' => $phone,
                        'result' => $result
                    ];
                    
                    return $this->renderer->render($response, 'register_account.php', $data);
                }
            }
        }
        
        // Redirect back to accounts page
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Verify 2FA for an account
     */
    public function verify2fa(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        $phone = $params['phone'] ?? '';
        $password = $params['password'] ?? '';
        
        if (!empty($phone) && !empty($password)) {
            $result = $this->accountManager->verify2FA($phone, $password);
        }
        
        // Redirect back to accounts page
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Check accounts for links
     */
    public function checkLinks(Request $request, Response $response): Response
    {
        $result = $this->accountManager->checkAllAccountsForLinks();
        
        // Check if this is an AJAX request
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $payload = json_encode($result);
            $response->getBody()->write($payload);
            
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Redirect back to accounts page
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * View private messages
     */
    public function privateMessages(Request $request, Response $response): Response
    {
        $allMessages = $this->accountManager->getAllPrivateMessages();
        $avalaiEnabled = $this->avalaiApi->isEnabled();
        $avalaiSettings = $this->avalaiApi->getSettings();
        
        $data = [
            'messages' => $allMessages,
            'avalaiEnabled' => $avalaiEnabled,
            'avalaiSettings' => $avalaiSettings
        ];
        
        return $this->renderer->render($response, 'private_messages.php', $data);
    }
    
    /**
     * View Telegram desktop-like interface
     */
    public function telegramDesktop(Request $request, Response $response): Response
    {
        $allMessages = $this->accountManager->getAllPrivateMessages();
        $avalaiEnabled = $this->avalaiApi->isEnabled();
        $avalaiSettings = $this->avalaiApi->getSettings();
        
        $data = [
            'messages' => $allMessages,
            'avalaiEnabled' => $avalaiEnabled,
            'avalaiSettings' => $avalaiSettings
        ];
        
        return $this->renderer->render($response, 'telegram_desktop.php', $data);
    }
    
    /**
     * Send message to a user
     */
    public function sendMessage(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        $phone = $params['phone'] ?? '';
        $userId = $params['user_id'] ?? '';
        $text = $params['text'] ?? '';
        
        $result = false;
        $aiResponse = null;
        
        if (!empty($phone) && !empty($userId) && !empty($text)) {
            $result = $this->accountManager->sendMessage($phone, $userId, $text);
            
            // If Avalai is enabled, generate AI response
            if ($this->avalaiApi->isEnabled()) {
                $aiResult = $this->avalaiApi->generateResponse($text, $userId);
                
                if ($aiResult['success']) {
                    $aiResponse = $aiResult['response'];
                    
                    // Send AI response if it was generated successfully
                    if ($aiResponse) {
                        $this->accountManager->sendMessage($phone, $userId, $aiResponse);
                    }
                }
            }
        }
        
        // Check if this is an AJAX request
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $data = [
                'success' => $result,
                'ai_response' => $aiResponse
            ];
            
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Redirect back to telegram desktop view
        return $response->withHeader('Location', '/telegram-desktop')->withStatus(302);
    }
    
    /**
     * Clear chat history
     */
    public function clearChatHistory(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $userId = $queryParams['user_id'] ?? null;
        
        // Clear Avalai chat history
        $this->avalaiApi->clearChatHistory($userId);
        
        // Redirect back to telegram desktop view
        return $response->withHeader('Location', '/telegram-desktop')->withStatus(302);
    }
}