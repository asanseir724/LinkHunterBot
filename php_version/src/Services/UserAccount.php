<?php

namespace App\Services;

use Exception;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Logger;

/**
 * Class UserAccount
 * 
 * این کلاس برای مدیریت حساب‌های کاربری تلگرام با استفاده از MadelineProto استفاده می‌شود.
 */
class UserAccount {
    /**
     * نام پوشه برای ذخیره سشن‌ها
     */
    const SESSION_PATH = 'sessions';
    
    /**
     * شماره تلفن حساب کاربری
     */
    private $phone;
    
    /**
     * آیا حساب متصل است؟
     */
    private $connected = false;
    
    /**
     * اطلاعات حساب کاربری
     */
    private $accountInfo = [];
    
    /**
     * نمونه MadelineProto API
     */
    private $madelineProto = null;
    
    /**
     * سازنده کلاس
     * 
     * @param string $phone شماره تلفن با فرمت بین‌المللی
     * @param array $accountInfo اطلاعات اضافی حساب کاربری (اختیاری)
     * @throws Exception در صورت خطا در ایجاد پوشه سشن
     */
    public function __construct($phone, array $accountInfo = []) {
        if (!is_dir(self::SESSION_PATH)) {
            if (!mkdir(self::SESSION_PATH, 0777, true)) {
                throw new Exception("خطا در ایجاد پوشه سشن: " . self::SESSION_PATH);
            }
        }
        
        $this->phone = $phone;
        $this->accountInfo = $accountInfo;
        
        // اگر اطلاعات وضعیت اتصال موجود باشد، آن را تنظیم کنید
        if (isset($accountInfo['connected'])) {
            $this->connected = (bool) $accountInfo['connected'];
        }
    }
    
    /**
     * ایجاد نمونه MadelineProto API
     * 
     * @return API نمونه MadelineProto API
     */
    public function createMadelineInstance() {
        $settings = new Settings;
        $settings->setAppInfo((new AppInfo)
            ->setApiId(12345)  // باید با مقدار واقعی جایگزین شود
            ->setApiHash('your_api_hash_here')  // باید با مقدار واقعی جایگزین شود
            ->setDeviceModel('Web Server')
            ->setSystemVersion('PHP ' . PHP_VERSION)
            ->setAppVersion('1.0.0')
            ->setLangCode('fa')
        );
        
        $settings->getLogger()->setLevel(Logger::LEVEL_WARNING);
        
        $sessionFile = $this->getSessionPath();
        
        try {
            $this->madelineProto = new API($sessionFile, $settings);
            return $this->madelineProto;
        } catch (Exception $e) {
            throw new Exception("خطا در ایجاد نمونه MadelineProto: " . $e->getMessage());
        }
    }
    
    /**
     * دریافت نمونه MadelineProto API
     * 
     * @return API نمونه MadelineProto API
     */
    public function getMadelineInstance() {
        if ($this->madelineProto === null) {
            $this->createMadelineInstance();
        }
        
        return $this->madelineProto;
    }
    
    /**
     * اتصال به حساب کاربری تلگرام
     * 
     * @return array نتیجه عملیات
     */
    public function connect() {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // تلاش برای ورود
            $authorization = $MadelineProto->phoneLogin($this->phone);
            
            if ($authorization['_'] === 'auth.sentCode') {
                return [
                    'success' => false,
                    'status' => 'code_needed',
                    'phone_code_hash' => $authorization['phone_code_hash'],
                    'message' => 'کد تأیید به تلفن شما ارسال شد. لطفاً آن را وارد کنید.'
                ];
            }
            
            $this->connected = true;
            return [
                'success' => true,
                'status' => 'connected',
                'message' => 'اتصال با موفقیت انجام شد.'
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * تأیید کد احراز هویت
     * 
     * @param string $code کد تأیید دریافتی
     * @param string $phoneCodeHash کد هش تلفن
     * @return array نتیجه عملیات
     */
    public function verifyCode($code, $phoneCodeHash) {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // تلاش برای تأیید کد
            $authorization = $MadelineProto->completePhoneLogin($code);
            
            if ($authorization['_'] === 'auth.authorization') {
                // اتصال موفقیت‌آمیز
                $this->connected = true;
                $this->updateAccountInfo();
                
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'ورود با موفقیت انجام شد.'
                ];
            } elseif ($authorization['_'] === 'account.password') {
                // نیاز به رمز عبور دو مرحله‌ای
                return [
                    'success' => false,
                    'status' => '2fa_needed',
                    'message' => 'این حساب دارای رمز عبور دو مرحله‌ای است. لطفاً رمز عبور را وارد کنید.'
                ];
            }
            
            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'خطای ناشناخته در تأیید کد.'
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * تأیید رمز عبور دو مرحله‌ای
     * 
     * @param string $password رمز عبور دو مرحله‌ای
     * @return array نتیجه عملیات
     */
    public function verify2FA($password) {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // تلاش برای ورود با رمز عبور دو مرحله‌ای
            $authorization = $MadelineProto->complete2faLogin($password);
            
            if ($authorization['_'] === 'auth.authorization') {
                // اتصال موفقیت‌آمیز
                $this->connected = true;
                $this->updateAccountInfo();
                
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'ورود با موفقیت انجام شد.'
                ];
            }
            
            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'خطای ناشناخته در تأیید رمز عبور دو مرحله‌ای.'
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * قطع اتصال حساب کاربری
     * 
     * @return array نتیجه عملیات
     */
    public function disconnect() {
        try {
            $sessionFile = $this->getSessionPath();
            
            // اگر فایل سشن وجود دارد، آن را پاک کنید
            if (file_exists($sessionFile)) {
                unlink($sessionFile);
            }
            
            $this->connected = false;
            $this->madelineProto = null;
            
            return [
                'success' => true,
                'message' => 'اتصال با موفقیت قطع شد.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در قطع اتصال: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی وضعیت اتصال حساب کاربری
     * 
     * @return bool آیا حساب متصل است؟
     */
    public function isConnected() {
        // ابتدا فایل سشن را بررسی کنید
        $sessionFile = $this->getSessionPath();
        if (!file_exists($sessionFile)) {
            $this->connected = false;
            return false;
        }
        
        // اگر قبلاً اتصال برقرار شده، وضعیت را بررسی کنید
        if ($this->connected) {
            try {
                $MadelineProto = $this->getMadelineInstance();
                $self = $MadelineProto->getSelf();
                
                // اگر اطلاعات کاربر دریافت شد، یعنی اتصال برقرار است
                if ($self) {
                    return true;
                }
            } catch (Exception $e) {
                // اگر خطایی رخ داد، یعنی اتصال قطع شده است
                $this->connected = false;
                return false;
            }
        }
        
        return $this->connected;
    }
    
    /**
     * به‌روزرسانی اطلاعات حساب کاربری
     * 
     * @return bool آیا به‌روزرسانی موفقیت‌آمیز بود؟
     */
    public function updateAccountInfo() {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            $self = $MadelineProto->getSelf();
            
            if ($self) {
                $this->accountInfo['first_name'] = $self['first_name'] ?? '';
                $this->accountInfo['last_name'] = $self['last_name'] ?? '';
                $this->accountInfo['username'] = $self['username'] ?? '';
                $this->accountInfo['phone'] = $this->phone;
                $this->accountInfo['user_id'] = $self['id'] ?? '';
                $this->accountInfo['connected'] = true;
                $this->accountInfo['last_check_time'] = time();
                
                return true;
            }
        } catch (Exception $e) {
            // در صورت خطا، چیزی را تغییر ندهید
        }
        
        return false;
    }
    
    /**
     * دریافت اطلاعات حساب کاربری
     * 
     * @return array اطلاعات حساب کاربری
     */
    public function getAccountInfo() {
        // اگر متصل هستیم و اطلاعات کاربر کامل نیست، سعی کنید آن را به‌روز کنید
        if ($this->isConnected() && 
            (empty($this->accountInfo['username']) || empty($this->accountInfo['first_name']))) {
            $this->updateAccountInfo();
        }
        
        // اطلاعات اصلی را اطمینان حاصل کنید
        $info = $this->accountInfo;
        $info['phone'] = $this->phone;
        $info['connected'] = $this->connected;
        
        return $info;
    }
    
    /**
     * دریافت پیام‌های خصوصی از یک چت
     * 
     * @param string $chatId شناسه چت یا نام کاربری
     * @param int $limit حداکثر تعداد پیام‌ها
     * @return array لیست پیام‌ها
     */
    public function getMessages($chatId, $limit = 100) {
        if (!$this->isConnected()) {
            return [];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            $messages = $MadelineProto->messages->getHistory([
                'peer' => $chatId,
                'limit' => $limit,
                'offset_id' => 0,
                'offset_date' => 0,
                'add_offset' => 0,
                'max_id' => 0,
                'min_id' => 0,
            ]);
            
            if (isset($messages['messages']) && is_array($messages['messages'])) {
                return $messages['messages'];
            }
        } catch (Exception $e) {
            // در صورت خطا، آرایه خالی برگردانید
        }
        
        return [];
    }
    
    /**
     * دریافت لیست چت‌ها
     * 
     * @param int $limit حداکثر تعداد چت‌ها
     * @return array لیست چت‌ها
     */
    public function getDialogs($limit = 100) {
        if (!$this->isConnected()) {
            return [];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            $dialogs = $MadelineProto->getDialogs();
            
            // محدود کردن تعداد نتایج
            if (count($dialogs) > $limit) {
                $dialogs = array_slice($dialogs, 0, $limit);
            }
            
            return $dialogs;
        } catch (Exception $e) {
            // در صورت خطا، آرایه خالی برگردانید
        }
        
        return [];
    }
    
    /**
     * ارسال پیام به یک چت
     * 
     * @param string $chatId شناسه چت یا نام کاربری
     * @param string $message متن پیام
     * @return array نتیجه عملیات
     */
    public function sendMessage($chatId, $message) {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'message' => 'حساب کاربری متصل نیست.'
            ];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            $result = $MadelineProto->messages->sendMessage([
                'peer' => $chatId,
                'message' => $message,
                'random_id' => random_int(0, PHP_INT_MAX),
            ]);
            
            return [
                'success' => true,
                'message' => 'پیام با موفقیت ارسال شد.',
                'result' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ارسال پیام: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * استخراج لینک‌ها از پیام‌های یک چت
     * 
     * @param string $chatId شناسه چت یا نام کاربری
     * @param int $limit حداکثر تعداد پیام‌ها
     * @return array لیست لینک‌ها
     */
    public function extractLinksFromChat($chatId, $limit = 100) {
        $messages = $this->getMessages($chatId, $limit);
        $links = [];
        
        foreach ($messages as $message) {
            if (isset($message['message']) && is_string($message['message'])) {
                // استخراج لینک‌های تلگرام از متن پیام
                $telegramLinks = $this->extractTelegramLinks($message['message']);
                $links = array_merge($links, $telegramLinks);
            }
        }
        
        // حذف لینک‌های تکراری
        return array_unique($links);
    }
    
    /**
     * استخراج لینک‌های تلگرام از متن
     * 
     * @param string $text متن پیام
     * @return array لیست لینک‌ها
     */
    private function extractTelegramLinks($text) {
        $links = [];
        
        // الگوهای مختلف لینک‌های تلگرام
        $patterns = [
            '/https?:\/\/t\.me\/([a-zA-Z0-9_]+)/i',
            '/https?:\/\/telegram\.me\/([a-zA-Z0-9_]+)/i',
            '/https?:\/\/t\.me\/joinchat\/([a-zA-Z0-9_-]+)/i',
            '/https?:\/\/telegram\.me\/joinchat\/([a-zA-Z0-9_-]+)/i',
            '/https?:\/\/t\.me\/\+([a-zA-Z0-9_-]+)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $match) {
                    $links[] = $match;
                }
            }
        }
        
        return $links;
    }
    
    /**
     * رسیدگی به استثنائات
     * 
     * @param Exception $e استثنا
     * @return array پاسخ خطا
     */
    private function handleException(Exception $e) {
        $message = $e->getMessage();
        
        // بررسی نوع خطا برای ارائه پیام خطای بهتر
        if (strpos($message, 'PHONE_NUMBER_INVALID') !== false) {
            return [
                'success' => false,
                'status' => 'invalid_phone',
                'message' => 'شماره تلفن نامعتبر است. لطفاً با فرمت صحیح وارد کنید (مثال: ۹۸۹۱۲۳۴۵۶۷۸۹+)'
            ];
        } elseif (strpos($message, 'PHONE_CODE_INVALID') !== false) {
            return [
                'success' => false,
                'status' => 'invalid_code',
                'message' => 'کد تأیید نامعتبر است. لطفاً دوباره تلاش کنید.'
            ];
        } elseif (strpos($message, 'PASSWORD_HASH_INVALID') !== false) {
            return [
                'success' => false,
                'status' => 'invalid_password',
                'message' => 'رمز عبور دو مرحله‌ای نادرست است. لطفاً دوباره تلاش کنید.'
            ];
        } elseif (strpos($message, 'FLOOD_WAIT') !== false) {
            // استخراج زمان انتظار
            preg_match('/FLOOD_WAIT_(\d+)/', $message, $matches);
            $waitTime = isset($matches[1]) ? (int) $matches[1] : 60;
            
            return [
                'success' => false,
                'status' => 'flood_wait',
                'message' => "محدودیت درخواست. لطفاً {$waitTime} ثانیه صبر کنید و دوباره تلاش کنید."
            ];
        }
        
        // خطای عمومی
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'خطا: ' . $message
        ];
    }
    
    /**
     * دریافت مسیر فایل سشن
     * 
     * @return string مسیر فایل سشن
     */
    private function getSessionPath() {
        // تبدیل شماره تلفن به یک نام فایل ایمن
        $safePhone = preg_replace('/[^0-9]/', '', $this->phone);
        return self::SESSION_PATH . '/account_' . $safePhone . '.madeline';
    }
}