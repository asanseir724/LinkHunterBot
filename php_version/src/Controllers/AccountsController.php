<?php

namespace App\Controllers;

use App\Services\AccountManager;
use App\Services\UserAccount;
use App\Services\LinkManager;
use Exception;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Controller برای مدیریت حساب‌های کاربری تلگرام
 */
class AccountsController {
    /**
     * مدیریت کننده حساب‌های کاربری
     * 
     * @var AccountManager
     */
    private $accountManager;
    
    /**
     * مدیریت کننده لینک‌ها
     * 
     * @var LinkManager
     */
    private $linkManager;
    
    /**
     * سازنده کلاس
     */
    public function __construct() {
        $this->accountManager = new AccountManager();
        $this->linkManager = new LinkManager();
    }
    
    /**
     * صفحه اصلی مدیریت حساب‌ها
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response) {
        $accounts = $this->accountManager->getAllAccounts();
        
        // بررسی وضعیت اتصال واقعی حساب‌ها
        foreach ($accounts as &$account) {
            try {
                $userAccount = new UserAccount($account['phone'], $account);
                $account['connected'] = $userAccount->isConnected();
            } catch (Exception $e) {
                $account['connected'] = false;
            }
        }
        
        ob_start();
        require __DIR__ . '/../../templates/accounts.php';
        $output = ob_get_clean();
        
        $response->getBody()->write($output);
        return $response;
    }
    
    /**
     * افزودن حساب کاربری جدید
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addAccount(Request $request, Response $response) {
        $params = $request->getParsedBody();
        $phone = $params['phone'] ?? '';
        
        if (empty($phone)) {
            $_SESSION['error'] = 'شماره تلفن الزامی است.';
            return $response->withRedirect('/accounts');
        }
        
        $result = $this->accountManager->addAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            return $response->withRedirect('/accounts/connect/' . urlencode($phone));
        } else {
            $_SESSION['error'] = $result['message'];
            return $response->withRedirect('/accounts');
        }
    }
    
    /**
     * حذف حساب کاربری
     * 
     * @param Request $request
     * @param Response $response
     * @param string $phone شماره تلفن
     * @return Response
     */
    public function removeAccount(Request $request, Response $response, $phone) {
        $result = $this->accountManager->removeAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        return $response->withRedirect('/accounts');
    }
    
    /**
     * اتصال به حساب کاربری
     * 
     * @param Request $request
     * @param Response $response
     * @param string $phone شماره تلفن
     * @return Response
     */
    public function connectAccount(Request $request, Response $response, $phone) {
        // استفاده از متد startLoginProcess به جای connectAccount
        $result = $this->accountManager->startLoginProcess($phone);
        
        if ($result['success']) {
            // اتصال موفقیت‌آمیز بوده
            $_SESSION['success'] = $result['message'];
            return $response->withRedirect('/accounts');
        } elseif ($result['status'] === 'code_needed') {
            // نیاز به کد تأیید
            $_SESSION['phone_code_hash'] = $result['phone_code_hash'] ?? '';
            return $this->showVerifyCodePage($response, $phone, $result['message']);
        } elseif ($result['status'] === '2fa_needed') {
            // نیاز به رمز عبور دو مرحله‌ای
            return $this->showVerify2FAPage($response, $phone, $result['message']);
        } else {
            // خطا در اتصال
            $_SESSION['error'] = $result['message'];
            return $response->withRedirect('/accounts');
        }
    }
    
    /**
     * قطع اتصال حساب کاربری
     * 
     * @param Request $request
     * @param Response $response
     * @param string $phone شماره تلفن
     * @return Response
     */
    public function disconnectAccount(Request $request, Response $response, $phone) {
        // استفاده از متد logout به جای disconnectAccount
        $result = $this->accountManager->logout($phone, true);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        return $response->withRedirect('/accounts');
    }
    
    /**
     * نمایش صفحه تأیید کد
     * 
     * @param Response $response
     * @param string $phone شماره تلفن
     * @param string $message پیام نمایش داده شده
     * @return Response
     */
    private function showVerifyCodePage(Response $response, $phone, $message = null) {
        $error = $message && strpos($message, 'خطا') !== false ? $message : null;
        
        ob_start();
        require __DIR__ . '/../../templates/verify_code.php';
        $output = ob_get_clean();
        
        $response->getBody()->write($output);
        return $response;
    }
    
    /**
     * نمایش صفحه تأیید رمز عبور دو مرحله‌ای
     * 
     * @param Response $response
     * @param string $phone شماره تلفن
     * @param string $message پیام نمایش داده شده
     * @return Response
     */
    private function showVerify2FAPage(Response $response, $phone, $message = null) {
        $error = $message && strpos($message, 'خطا') !== false ? $message : null;
        
        ob_start();
        require __DIR__ . '/../../templates/verify_2fa.php';
        $output = ob_get_clean();
        
        $response->getBody()->write($output);
        return $response;
    }
    
    /**
     * تأیید کد احراز هویت
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function verifyCode(Request $request, Response $response) {
        $params = $request->getParsedBody();
        $phone = $params['phone'] ?? '';
        $code = $params['code'] ?? '';
        $phoneCodeHash = $_SESSION['phone_code_hash'] ?? '';
        
        if (empty($phone) || empty($code)) {
            $_SESSION['error'] = 'شماره تلفن و کد تأیید الزامی هستند.';
            return $response->withRedirect('/accounts');
        }
        
        // استفاده از متد submitCode به جای verifyCode
        $result = $this->accountManager->submitCode($phone, $code, $phoneCodeHash);
        
        if ($result['success']) {
            // ورود موفقیت‌آمیز
            $_SESSION['success'] = $result['message'];
            // پاک کردن هش کد تلفن از سشن
            unset($_SESSION['phone_code_hash']);
            return $response->withRedirect('/accounts');
        } elseif ($result['status'] === '2fa_needed') {
            // نیاز به رمز عبور دو مرحله‌ای
            return $this->showVerify2FAPage($response, $phone, $result['message']);
        } else {
            // خطا در تأیید کد
            return $this->showVerifyCodePage($response, $phone, $result['message']);
        }
    }
    
    /**
     * تأیید رمز عبور دو مرحله‌ای
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function verify2FA(Request $request, Response $response) {
        $params = $request->getParsedBody();
        $phone = $params['phone'] ?? '';
        $password = $params['password'] ?? '';
        
        if (empty($phone) || empty($password)) {
            $_SESSION['error'] = 'شماره تلفن و رمز عبور الزامی هستند.';
            return $response->withRedirect('/accounts');
        }
        
        // استفاده از متد submit2FA به جای verify2FA
        $result = $this->accountManager->submit2FA($phone, $password);
        
        if ($result['success']) {
            // ورود موفقیت‌آمیز
            $_SESSION['success'] = $result['message'];
            return $response->withRedirect('/accounts');
        } else {
            // خطا در تأیید رمز عبور
            return $this->showVerify2FAPage($response, $phone, $result['message']);
        }
    }
    
    /**
     * بررسی لینک‌ها در حساب‌های کاربری متصل
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function checkAccountsForLinks(Request $request, Response $response) {
        $result = $this->accountManager->extractLinksFromAccounts($this->linkManager);
        
        $message = "بررسی کامل شد: ";
        $message .= "{$result['processed_accounts']} حساب از {$result['total_accounts']} حساب بررسی شد. ";
        $message .= "{$result['new_links']} لینک جدید از مجموع {$result['total_links']} لینک پیدا شد.";
        
        if (!empty($result['errors'])) {
            $message .= " خطاها: " . implode(", ", $result['errors']);
            $_SESSION['warning'] = $message;
        } else {
            $_SESSION['success'] = $message;
        }
        
        return $response->withRedirect('/accounts');
    }
    
    /**
     * نمایش پیام‌های خصوصی با رابط کاربری تلگرام دسکتاپ
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function telegramDesktop(Request $request, Response $response) {
        $accounts = $this->accountManager->getConnectedAccounts(true);
        $chats = [];
        $messages = [];
        $selectedAccount = null;
        $selectedChat = null;
        
        // بررسی انتخاب اکانت و چت
        $queryParams = $request->getQueryParams();
        $accountPhone = $queryParams['account'] ?? null;
        $chatId = $queryParams['chat'] ?? null;
        
        if (!empty($accountPhone) && isset($accounts[$accountPhone])) {
            $selectedAccount = $accounts[$accountPhone];
            
            try {
                // ایجاد نمونه UserAccount از طریق AccountManager
                $userAccount = $this->accountManager->createUserAccount($accountPhone);
                
                // دریافت لیست چت‌ها
                $dialogs = $userAccount->getDialogs();
                
                foreach ($dialogs as $peer => $dialog) {
                    $chats[] = [
                        'id' => $peer,
                        'title' => $dialog['title'] ?? $peer,
                        'username' => $dialog['username'] ?? null,
                        'photo' => $dialog['photo'] ?? null,
                        'last_message' => $dialog['message'] ?? null,
                        'type' => $dialog['type'] ?? 'unknown'
                    ];
                }
                
                // اگر چت انتخاب شده، پیام‌های آن را دریافت کن
                if (!empty($chatId)) {
                    $selectedChat = $chatId;
                    $messages = $userAccount->getMessages($chatId, 50);
                }
            } catch (Throwable $e) {
                $_SESSION['error'] = 'خطا در دریافت اطلاعات چت: ' . $e->getMessage();
            }
        }
        
        ob_start();
        require __DIR__ . '/../../templates/telegram_desktop.php';
        $output = ob_get_clean();
        
        $response->getBody()->write($output);
        return $response;
    }
    
    /**
     * ارسال پیام از طریق رابط کاربری تلگرام دسکتاپ
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendMessage(Request $request, Response $response) {
        $params = $request->getParsedBody();
        $accountPhone = $params['account'] ?? '';
        $chatId = $params['chat'] ?? '';
        $message = $params['message'] ?? '';
        
        if (empty($accountPhone) || empty($chatId) || empty($message)) {
            $_SESSION['error'] = 'اطلاعات ارسال پیام ناقص است.';
            return $response->withRedirect('/telegram-desktop');
        }
        
        // استفاده از متد sendMessage کلاس AccountManager به جای ایجاد نمونه مستقیم UserAccount
        $result = $this->accountManager->sendMessage($accountPhone, $chatId, $message);
        
        if ($result['success']) {
            $_SESSION['success'] = 'پیام با موفقیت ارسال شد.';
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        return $response->withRedirect('/telegram-desktop?account=' . urlencode($accountPhone) . '&chat=' . urlencode($chatId));
    }
}