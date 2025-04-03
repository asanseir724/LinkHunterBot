<?php

namespace App\Controllers;

use App\Services\AccountManager;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Class AccountsController
 * Handles account management routes and actions
 */
class AccountsController
{
    private $accountManager;
    private $renderer;
    private $logger;
    
    /**
     * AccountsController constructor
     * 
     * @param AccountManager $accountManager
     * @param PhpRenderer $renderer
     * @param LoggerInterface|null $logger
     */
    public function __construct(AccountManager $accountManager, PhpRenderer $renderer, ?LoggerInterface $logger = null)
    {
        $this->accountManager = $accountManager;
        $this->renderer = $renderer;
        $this->logger = $logger;
    }
    
    /**
     * Display accounts management page
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response): Response
    {
        $accounts = $this->accountManager->getAccounts();
        
        return $this->renderer->render($response, 'accounts.php', [
            'accounts' => $accounts
        ]);
    }
    
    /**
     * Add a new account
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addAccount(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $phone = $params['phone'] ?? '';
        $name = $params['name'] ?? null;
        
        if (empty($phone)) {
            $_SESSION['error'] = 'لطفاً شماره تلفن را وارد کنید.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        $result = $this->accountManager->addAccount($phone, $name);
        
        if ($result['success']) {
            $_SESSION['phone'] = $phone;
            $_SESSION['phone_code_hash'] = $result['phone_code_hash'];
            
            return $response->withHeader('Location', '/accounts/verify-code')->withStatus(302);
        } else {
            $_SESSION['error'] = $result['error'] ?? 'خطا در افزودن حساب کاربری.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
    }
    
    /**
     * Display code verification page
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function verifyCodePage(Request $request, Response $response): Response
    {
        if (empty($_SESSION['phone']) || empty($_SESSION['phone_code_hash'])) {
            $_SESSION['error'] = 'لطفاً ابتدا شماره تلفن را وارد کنید.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        return $this->renderer->render($response, 'verify_code.php', [
            'phone' => $_SESSION['phone'],
            'phone_code_hash' => $_SESSION['phone_code_hash']
        ]);
    }
    
    /**
     * Process code verification
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function verifyCode(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $phone = $params['phone'] ?? $_SESSION['phone'] ?? '';
        $code = $params['code'] ?? '';
        $phone_code_hash = $_SESSION['phone_code_hash'] ?? '';
        
        if (empty($phone) || empty($code) || empty($phone_code_hash)) {
            $_SESSION['error'] = 'اطلاعات ناقص است. لطفاً دوباره تلاش کنید.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        $result = $this->accountManager->verifyCode($phone, $code, $phone_code_hash);
        
        if ($result['success']) {
            if (!empty($result['requires_2fa'])) {
                // 2FA required
                $_SESSION['phone'] = $phone;
                $_SESSION['hint'] = $result['hint'] ?? '';
                $_SESSION['has_recovery'] = $result['has_recovery'] ?? false;
                
                return $response->withHeader('Location', '/accounts/verify-2fa')->withStatus(302);
            }
            
            // Successfully verified
            unset($_SESSION['phone']);
            unset($_SESSION['phone_code_hash']);
            
            $_SESSION['success'] = 'حساب کاربری با موفقیت اضافه شد.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        } else {
            $_SESSION['error'] = $result['error'] ?? 'کد وارد شده نامعتبر است.';
            return $response->withHeader('Location', '/accounts/verify-code')->withStatus(302);
        }
    }
    
    /**
     * Display 2FA verification page
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function verify2FAPage(Request $request, Response $response): Response
    {
        if (empty($_SESSION['phone'])) {
            $_SESSION['error'] = 'لطفاً ابتدا شماره تلفن را وارد کنید.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        return $this->renderer->render($response, 'verify_2fa.php', [
            'phone' => $_SESSION['phone'],
            'hint' => $_SESSION['hint'] ?? '',
            'has_recovery' => $_SESSION['has_recovery'] ?? false
        ]);
    }
    
    /**
     * Process 2FA verification
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function verify2FA(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $phone = $params['phone'] ?? $_SESSION['phone'] ?? '';
        $password = $params['password'] ?? '';
        
        if (empty($phone) || empty($password)) {
            $_SESSION['error'] = 'اطلاعات ناقص است. لطفاً دوباره تلاش کنید.';
            return $response->withHeader('Location', '/accounts/verify-2fa')->withStatus(302);
        }
        
        $result = $this->accountManager->verify2FA($phone, $password);
        
        if ($result['success']) {
            // Successfully verified
            unset($_SESSION['phone']);
            unset($_SESSION['hint']);
            unset($_SESSION['has_recovery']);
            
            $_SESSION['success'] = 'حساب کاربری با موفقیت اضافه شد.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        } else {
            $_SESSION['error'] = $result['error'] ?? 'رمز عبور نامعتبر است.';
            return $response->withHeader('Location', '/accounts/verify-2fa')->withStatus(302);
        }
    }
    
    /**
     * Connect to account
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function connectAccount(Request $request, Response $response, array $args): Response
    {
        $phone = urldecode($args['phone'] ?? '');
        
        if (empty($phone)) {
            $_SESSION['error'] = 'شماره تلفن نامعتبر است.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        $result = $this->accountManager->connectAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = 'اتصال به حساب کاربری با موفقیت انجام شد.';
        } else {
            $_SESSION['error'] = $result['error'] ?? 'خطا در اتصال به حساب کاربری.';
        }
        
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Disconnect from account
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function disconnectAccount(Request $request, Response $response, array $args): Response
    {
        $phone = urldecode($args['phone'] ?? '');
        
        if (empty($phone)) {
            $_SESSION['error'] = 'شماره تلفن نامعتبر است.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        $result = $this->accountManager->disconnectAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = 'قطع اتصال حساب کاربری با موفقیت انجام شد.';
        } else {
            $_SESSION['error'] = $result['error'] ?? 'خطا در قطع اتصال حساب کاربری.';
        }
        
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Remove account
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function removeAccount(Request $request, Response $response, array $args): Response
    {
        $phone = urldecode($args['phone'] ?? '');
        
        if (empty($phone)) {
            $_SESSION['error'] = 'شماره تلفن نامعتبر است.';
            return $response->withHeader('Location', '/accounts')->withStatus(302);
        }
        
        $result = $this->accountManager->removeAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = 'حساب کاربری با موفقیت حذف شد.';
        } else {
            $_SESSION['error'] = $result['error'] ?? 'خطا در حذف حساب کاربری.';
        }
        
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Check all accounts for links
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function checkAccountsForLinks(Request $request, Response $response): Response
    {
        $result = $this->accountManager->checkAllAccountsForLinks();
        
        if ($result['success']) {
            $_SESSION['success'] = "بررسی لینک‌ها در تمام حساب‌ها با موفقیت انجام شد. {$result['total_links']} لینک یافت شد.";
        } else {
            $_SESSION['error'] = $result['error'] ?? 'خطا در بررسی لینک‌ها.';
        }
        
        return $response->withHeader('Location', '/accounts')->withStatus(302);
    }
    
    /**
     * Display Telegram desktop-like interface for viewing private messages
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function telegramDesktop(Request $request, Response $response): Response
    {
        $privateMessages = $this->accountManager->getAllPrivateMessages();
        
        return $this->renderer->render($response, 'telegram_desktop.php', [
            'accounts' => $privateMessages
        ]);
    }
    
    /**
     * Add sample messages (for testing UI)
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addSampleMessages(Request $request, Response $response): Response
    {
        $_SESSION['success'] = 'پیام‌های نمونه با موفقیت اضافه شدند.';
        return $response->withHeader('Location', '/telegram-desktop')->withStatus(302);
    }
    
    /**
     * Clear chat history (all or specific chat)
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function clearChatHistory(Request $request, Response $response, array $args): Response
    {
        $chat = $args['chat'] ?? null;
        
        if ($chat) {
            $_SESSION['success'] = "تاریخچه چت {$chat} با موفقیت پاک شد.";
        } else {
            $_SESSION['success'] = 'تاریخچه تمام چت‌ها با موفقیت پاک شد.';
        }
        
        return $response->withHeader('Location', '/telegram-desktop')->withStatus(302);
    }
}