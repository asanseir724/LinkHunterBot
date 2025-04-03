<?php

namespace App\Controllers;

use App\Services\AccountManager;
use App\Services\UserAccount;
use App\Services\LinkManager;
use Exception;

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
     */
    public function index() {
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
        
        return require __DIR__ . '/../../templates/accounts.php';
    }
    
    /**
     * افزودن حساب کاربری جدید
     */
    public function addAccount() {
        $phone = $_POST['phone'] ?? '';
        
        if (empty($phone)) {
            $_SESSION['error'] = 'شماره تلفن الزامی است.';
            header('Location: /accounts');
            exit;
        }
        
        $result = $this->accountManager->addAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            header('Location: /accounts/connect/' . urlencode($phone));
        } else {
            $_SESSION['error'] = $result['message'];
            header('Location: /accounts');
        }
        exit;
    }
    
    /**
     * حذف حساب کاربری
     * 
     * @param string $phone شماره تلفن
     */
    public function removeAccount($phone) {
        $result = $this->accountManager->removeAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: /accounts');
        exit;
    }
    
    /**
     * اتصال به حساب کاربری
     * 
     * @param string $phone شماره تلفن
     */
    public function connectAccount($phone) {
        $result = $this->accountManager->connectAccount($phone);
        
        if ($result['success']) {
            // اتصال موفقیت‌آمیز بوده
            $_SESSION['success'] = $result['message'];
            header('Location: /accounts');
            exit;
        } elseif ($result['status'] === 'code_needed') {
            // نیاز به کد تأیید
            $_SESSION['phone_code_hash'] = $result['phone_code_hash'] ?? '';
            return $this->showVerifyCodePage($phone, $result['message']);
        } elseif ($result['status'] === '2fa_needed') {
            // نیاز به رمز عبور دو مرحله‌ای
            return $this->showVerify2FAPage($phone, $result['message']);
        } else {
            // خطا در اتصال
            $_SESSION['error'] = $result['message'];
            header('Location: /accounts');
            exit;
        }
    }
    
    /**
     * قطع اتصال حساب کاربری
     * 
     * @param string $phone شماره تلفن
     */
    public function disconnectAccount($phone) {
        $result = $this->accountManager->disconnectAccount($phone);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: /accounts');
        exit;
    }
    
    /**
     * نمایش صفحه تأیید کد
     * 
     * @param string $phone شماره تلفن
     * @param string $message پیام نمایش داده شده
     */
    private function showVerifyCodePage($phone, $message = null) {
        $error = $message && strpos($message, 'خطا') !== false ? $message : null;
        return require __DIR__ . '/../../templates/verify_code.php';
    }
    
    /**
     * نمایش صفحه تأیید رمز عبور دو مرحله‌ای
     * 
     * @param string $phone شماره تلفن
     * @param string $message پیام نمایش داده شده
     */
    private function showVerify2FAPage($phone, $message = null) {
        $error = $message && strpos($message, 'خطا') !== false ? $message : null;
        return require __DIR__ . '/../../templates/verify_2fa.php';
    }
    
    /**
     * تأیید کد احراز هویت
     */
    public function verifyCode() {
        $phone = $_POST['phone'] ?? '';
        $code = $_POST['code'] ?? '';
        
        if (empty($phone) || empty($code)) {
            $_SESSION['error'] = 'شماره تلفن و کد تأیید الزامی هستند.';
            header('Location: /accounts');
            exit;
        }
        
        $result = $this->accountManager->verifyCode($phone, $code);
        
        if ($result['success']) {
            // ورود موفقیت‌آمیز
            $_SESSION['success'] = $result['message'];
            header('Location: /accounts');
            exit;
        } elseif ($result['status'] === '2fa_needed') {
            // نیاز به رمز عبور دو مرحله‌ای
            return $this->showVerify2FAPage($phone, $result['message']);
        } else {
            // خطا در تأیید کد
            return $this->showVerifyCodePage($phone, $result['message']);
        }
    }
    
    /**
     * تأیید رمز عبور دو مرحله‌ای
     */
    public function verify2FA() {
        $phone = $_POST['phone'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($phone) || empty($password)) {
            $_SESSION['error'] = 'شماره تلفن و رمز عبور الزامی هستند.';
            header('Location: /accounts');
            exit;
        }
        
        $result = $this->accountManager->verify2FA($phone, $password);
        
        if ($result['success']) {
            // ورود موفقیت‌آمیز
            $_SESSION['success'] = $result['message'];
            header('Location: /accounts');
            exit;
        } else {
            // خطا در تأیید رمز عبور
            return $this->showVerify2FAPage($phone, $result['message']);
        }
    }
    
    /**
     * بررسی لینک‌ها در حساب‌های کاربری متصل
     */
    public function checkAccountsForLinks() {
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
        
        header('Location: /accounts');
        exit;
    }
    
    /**
     * نمایش پیام‌های خصوصی
     */
    public function telegramDesktop() {
        $accounts = $this->accountManager->getConnectedAccounts(true);
        $chats = [];
        $messages = [];
        $selectedAccount = null;
        $selectedChat = null;
        
        // بررسی انتخاب اکانت و چت
        $accountPhone = $_GET['account'] ?? null;
        $chatId = $_GET['chat'] ?? null;
        
        if (!empty($accountPhone) && isset($accounts[$accountPhone])) {
            $selectedAccount = $accounts[$accountPhone];
            
            try {
                $userAccount = new UserAccount($accountPhone, $selectedAccount);
                
                // دریافت لیست چت‌ها
                $dialogs = $userAccount->getDialogs();
                
                foreach ($dialogs as $peer => $dialog) {
                    $chats[] = [
                        'id' => $peer,
                        'title' => $dialog['title'] ?? $peer,
                        'username' => $dialog['username'] ?? null,
                        'photo' => $dialog['photo'] ?? null,
                        'last_message' => $dialog['message'] ?? null
                    ];
                }
                
                // اگر چت انتخاب شده، پیام‌های آن را دریافت کن
                if (!empty($chatId)) {
                    $selectedChat = $chatId;
                    $messages = $userAccount->getMessages($chatId, 50);
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'خطا در دریافت اطلاعات چت: ' . $e->getMessage();
            }
        }
        
        return require __DIR__ . '/../../templates/telegram_desktop.php';
    }
}