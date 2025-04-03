<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Database\Mysql;
use danog\MadelineProto\Settings\Database\Memory;
use danog\MadelineProto\Settings\Database\Postgres;
use danog\MadelineProto\Settings\Database\Redis;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Exception;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\TON\APIFactory as TONAPIFactory;
use Throwable;

/**
 * کلاس مدیریت حساب‌های کاربری تلگرام با استفاده از MadelineProto
 */
class UserAccount
{
    /**
     * مسیر ذخیره‌سازی جلسه‌ها
     */
    const SESSION_PATH = 'sessions';

    /**
     * شماره تلفن حساب کاربری (با فرمت بین‌المللی)
     */
    private string $phone;

    /**
     * آیا حساب متصل است؟
     */
    private bool $connected = false;

    /**
     * اطلاعات حساب کاربری
     */
    private array $accountInfo = [];

    /**
     * نمونه API مدلاین پروتو
     */
    private ?API $madelineProto = null;

    /**
     * API ID برای MadelineProto
     */
    private int $apiId;

    /**
     * API Hash برای MadelineProto
     */
    private string $apiHash;

    /**
     * سازنده کلاس
     * 
     * @param string $phone شماره تلفن با فرمت بین‌المللی (مثال: 989123456789)
     * @param array $accountInfo اطلاعات اضافی حساب کاربری (اختیاری)
     * @param int|null $apiId API ID تلگرام (اختیاری، از متغیرهای محیطی استفاده می‌کند)
     * @param string|null $apiHash API Hash تلگرام (اختیاری، از متغیرهای محیطی استفاده می‌کند)
     * 
     * @throws \Exception در صورت بروز خطا هنگام ایجاد پوشه جلسه
     */
    public function __construct(
        string $phone,
        array $accountInfo = [],
        ?int $apiId = null,
        ?string $apiHash = null
    ) {
        // اطمینان از وجود پوشه session
        if (!is_dir(self::SESSION_PATH)) {
            if (!mkdir(self::SESSION_PATH, 0777, true)) {
                throw new \Exception('خطا در ایجاد پوشه جلسه: ' . self::SESSION_PATH);
            }
        }

        // تنظیم شماره تلفن (حذف "+" از ابتدای شماره اگر وجود داشته باشد)
        $this->phone = ltrim($phone, '+');

        // تنظیم API ID و API Hash
        $this->apiId = $apiId ?? (int)($_ENV['TELEGRAM_API_ID'] ?? 0);
        $this->apiHash = $apiHash ?? ($_ENV['TELEGRAM_API_HASH'] ?? '');

        // اگر API ID یا API Hash مقداردهی نشده باشند
        if (empty($this->apiId) || empty($this->apiHash)) {
            throw new \Exception('API ID و API Hash تلگرام باید مقداردهی شوند. لطفاً این مقادیر را در فایل .env تنظیم کنید.');
        }

        // ذخیره اطلاعات حساب
        $this->accountInfo = $accountInfo;

        // اگر اطلاعات وضعیت اتصال موجود باشد، آن را تنظیم کنید
        if (isset($accountInfo['connected'])) {
            $this->connected = (bool)$accountInfo['connected'];
        }
    }

    /**
     * ایجاد تنظیمات پیش‌فرض برای MadelineProto
     * 
     * @return Settings تنظیمات MadelineProto
     */
    private function createSettings(): Settings
    {
        $settings = new Settings;

        // تنظیمات اپلیکیشن
        $settings->setAppInfo((new AppInfo)
            ->setApiId($this->apiId)
            ->setApiHash($this->apiHash)
            ->setLangCode('fa')
            ->setAppVersion('1.0.0')
            ->setDeviceModel('Web Server')
            ->setSystemVersion('PHP ' . PHP_VERSION)
        );

        // تنظیمات پیشرفته
        $settings->getConnection()
            ->setMaxMediaSocketCount(30)        // تعداد سوکت‌های همزمان برای آپلود/دانلود مدیا
            ->setMaxFileSize(40 * 1024 * 1024); // حداکثر اندازه فایل: 40 مگابایت
        
        // تنظیمات لاگ
        $settings->getLogger()
            ->setLevel(Logger::LEVEL_WARNING)   // سطح لاگ: فقط هشدارها و خطاها
            ->setMaxSize(5 * 1024 * 1024);      // حداکثر اندازه فایل لاگ: 5 مگابایت

        // تنظیم پایگاه داده (استفاده از حافظه برای سادگی)
        $settings->setDb((new Memory));

        // تنظیمات پیشرفته بیشتر
        $settings->getPeer()
            ->setCacheAllPeersOnStartup(false)  // عدم کش‌کردن تمام مخاطبین در شروع (برای عملکرد بهتر)
            ->setFullFetch(false);              // عدم دریافت اطلاعات کامل همه مخاطبین

        return $settings;
    }

    /**
     * ایجاد نمونه MadelineProto API یا بازیابی آن اگر قبلاً ایجاد شده است
     * 
     * @return API نمونه MadelineProto API
     * @throws \Exception در صورت بروز خطا هنگام ایجاد نمونه MadelineProto
     */
    public function getMadelineInstance(): API
    {
        if ($this->madelineProto === null) {
            $sessionFile = $this->getSessionPath();
            $settings = $this->createSettings();
            
            try {
                // ایجاد یا بازیابی نمونه MadelineProto
                $this->madelineProto = new API($sessionFile, $settings);
                
                // تنظیم حالت پیش‌فرض برای تجزیه پیام
                $this->madelineProto->setParseMode(ParseMode::HTML);
                
                return $this->madelineProto;
            } catch (Throwable $e) {
                throw new \Exception('خطا در ایجاد یا بازیابی نمونه MadelineProto: ' . $e->getMessage(), 0, $e);
            }
        }
        
        return $this->madelineProto;
    }

    /**
     * آغاز فرآیند ورود به حساب کاربری تلگرام
     * 
     * @return array نتیجه عملیات
     */
    public function startLogin(): array
    {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // بررسی وضعیت ورود فعلی
            if ($MadelineProto->getAuthorization() === API::LOGGED_IN) {
                $this->connected = true;
                $this->updateAccountInfo();
                
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'حساب کاربری در حال حاضر متصل است.'
                ];
            }
            
            // ارسال کد تأیید به شماره تلفن
            $sentCode = $MadelineProto->phoneLogin($this->phone);
            
            return [
                'success' => false,
                'status' => 'code_needed',
                'phone_code_hash' => $sentCode['phone_code_hash'],
                'message' => 'کد تأیید به تلفن شما ارسال شد. لطفاً آن را وارد کنید.',
                'type' => $sentCode['type']['_'],
                'timeout' => $sentCode['timeout'] ?? 60
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در شروع فرآیند ورود: ' . $e->getMessage()
            ];
        }
    }

    /**
     * تأیید کد احراز هویت
     * 
     * @param string $code کد تأیید دریافتی
     * @param string $phoneCodeHash هش کد تلفن
     * @return array نتیجه عملیات
     */
    public function submitCode(string $code, string $phoneCodeHash): array
    {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // ارسال کد تأیید به سرور تلگرام
            $result = $MadelineProto->completePhoneLogin($code);
            
            // بررسی نتیجه
            if ($result['_'] === 'auth.authorization') {
                $this->connected = true;
                $this->updateAccountInfo();
                
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'ورود با موفقیت انجام شد.'
                ];
            } elseif ($result['_'] === 'account.password') {
                // نیاز به رمز عبور دو مرحله‌ای
                return [
                    'success' => false,
                    'status' => '2fa_needed',
                    'message' => 'این حساب دارای احراز هویت دو مرحله‌ای است. لطفاً رمز عبور خود را وارد کنید.',
                    'hint' => $result['hint'] ?? '',
                    'has_recovery' => $result['has_recovery'] ?? false
                ];
            } elseif ($result['_'] === 'account.needSignup') {
                // نیاز به ثبت‌نام (برای شماره‌هایی که قبلاً در تلگرام ثبت‌نام نکرده‌اند)
                return [
                    'success' => false,
                    'status' => 'signup_needed',
                    'message' => 'این شماره تلفن در تلگرام ثبت‌نام نشده است. لطفاً ابتدا در تلگرام ثبت‌نام کنید.'
                ];
            }
            
            // حالت غیرمنتظره
            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'خطای ناشناخته در تأیید کد.',
                'result' => $result
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در تأیید کد: ' . $e->getMessage()
            ];
        }
    }

    /**
     * تأیید رمز عبور دو مرحله‌ای
     * 
     * @param string $password رمز عبور
     * @return array نتیجه عملیات
     */
    public function submit2FA(string $password): array
    {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // ارسال رمز عبور به سرور تلگرام
            $result = $MadelineProto->complete2faLogin($password);
            
            if ($result['_'] === 'auth.authorization') {
                $this->connected = true;
                $this->updateAccountInfo();
                
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'ورود با موفقیت انجام شد.'
                ];
            }
            
            // حالت غیرمنتظره
            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'خطای ناشناخته در تأیید رمز عبور دو مرحله‌ای.',
                'result' => $result
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در تأیید رمز عبور دو مرحله‌ای: ' . $e->getMessage()
            ];
        }
    }

    /**
     * درخواست بازیابی حساب کاربری از طریق ایمیل
     * 
     * @param string $emailPattern الگوی ایمیل بازیابی (اختیاری)
     * @return array نتیجه عملیات
     */
    public function requestRecovery(string $emailPattern = ''): array
    {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // درخواست بازیابی از طریق ایمیل
            $result = $MadelineProto->requestRecovery([
                'email_pattern' => $emailPattern
            ]);
            
            if (isset($result['email_pattern'])) {
                return [
                    'success' => true,
                    'status' => 'recovery_email_sent',
                    'message' => 'ایمیل بازیابی به آدرس ' . $result['email_pattern'] . ' ارسال شد.',
                    'email_pattern' => $result['email_pattern']
                ];
            }
            
            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'خطای ناشناخته در درخواست بازیابی.',
                'result' => $result
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در درخواست بازیابی: ' . $e->getMessage()
            ];
        }
    }

    /**
     * بازیابی حساب با کد ارسال شده به ایمیل
     * 
     * @param string $code کد دریافتی از ایمیل
     * @param string $newPassword رمز عبور جدید برای 2FA
     * @return array نتیجه عملیات
     */
    public function recoverAccount(string $code, string $newPassword = ''): array
    {
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // بازیابی حساب با کد
            $result = $MadelineProto->recoverPassword([
                'code' => $code,
                'new_settings' => [
                    'new_password' => $newPassword,
                    'email' => '', // ایمیل جدید (اختیاری)
                    'hint' => 'راهنما برای رمز عبور جدید'
                ]
            ]);
            
            if ($result['_'] === 'auth.authorization') {
                $this->connected = true;
                $this->updateAccountInfo();
                
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'بازیابی حساب با موفقیت انجام شد.'
                ];
            }
            
            return [
                'success' => false,
                'status' => 'unknown',
                'message' => 'خطای ناشناخته در بازیابی حساب.',
                'result' => $result
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در بازیابی حساب: ' . $e->getMessage()
            ];
        }
    }

    /**
     * خروج از حساب کاربری
     * 
     * @param bool $deleteSession آیا فایل جلسه باید حذف شود؟
     * @return array نتیجه عملیات
     */
    public function logout(bool $deleteSession = true): array
    {
        try {
            if ($this->madelineProto !== null) {
                try {
                    // تلاش برای خروج از سرور تلگرام
                    $this->madelineProto->logout();
                } catch (Throwable $e) {
                    // ممکن است خروج با خطا مواجه شود، اما ما همچنان می‌خواهیم جلسه محلی را حذف کنیم
                }
            }
            
            // پاک کردن جلسه محلی
            if ($deleteSession) {
                $sessionFile = $this->getSessionPath();
                if (file_exists($sessionFile)) {
                    unlink($sessionFile);
                }
            }
            
            $this->connected = false;
            $this->madelineProto = null;
            
            return [
                'success' => true,
                'status' => 'logged_out',
                'message' => 'خروج از حساب کاربری با موفقیت انجام شد.'
            ];
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در خروج از حساب کاربری: ' . $e->getMessage()
            ];
        }
    }

    /**
     * بررسی وضعیت اتصال حساب کاربری
     * 
     * @return bool آیا حساب متصل است؟
     */
    public function isConnected(): bool
    {
        try {
            // بررسی وجود فایل جلسه
            $sessionFile = $this->getSessionPath();
            if (!file_exists($sessionFile)) {
                $this->connected = false;
                return false;
            }
            
            // بررسی وضعیت اتصال
            $MadelineProto = $this->getMadelineInstance();
            $authState = $MadelineProto->getAuthorization();
            
            $this->connected = ($authState === API::LOGGED_IN);
            
            if ($this->connected) {
                // تلاش برای دریافت اطلاعات کاربر برای تأیید نهایی
                $this->updateAccountInfo();
            }
            
            return $this->connected;
            
        } catch (Throwable $e) {
            // در صورت بروز هر خطایی، فرض می‌کنیم اتصال برقرار نیست
            $this->connected = false;
            return false;
        }
    }

    /**
     * به‌روزرسانی اطلاعات حساب کاربری
     * 
     * @return bool آیا به‌روزرسانی موفقیت‌آمیز بود؟
     */
    public function updateAccountInfo(): bool
    {
        // اگر متصل نیستیم، نمی‌توانیم اطلاعات را به‌روز کنیم
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // دریافت اطلاعات کاربر فعلی
            $user = $MadelineProto->getSelf();
            
            if ($user) {
                // ذخیره اطلاعات کاربر
                $this->accountInfo['user_id'] = $user['id'] ?? '';
                $this->accountInfo['first_name'] = $user['first_name'] ?? '';
                $this->accountInfo['last_name'] = $user['last_name'] ?? '';
                $this->accountInfo['username'] = $user['username'] ?? '';
                $this->accountInfo['phone'] = $user['phone'] ?? $this->phone;
                $this->accountInfo['connected'] = true;
                $this->accountInfo['last_check_time'] = time();
                $this->accountInfo['photos'] = $user['photos'] ?? [];
                $this->accountInfo['status'] = $user['status'] ?? [];
                
                return true;
            }
            
        } catch (Throwable $e) {
            // اگر خطایی رخ داد، اطلاعات را به‌روز نمی‌کنیم
        }
        
        return false;
    }

    /**
     * دریافت اطلاعات حساب کاربری
     * 
     * @return array اطلاعات حساب کاربری
     */
    public function getAccountInfo(): array
    {
        // اگر متصل هستیم و اطلاعات ناقص است، ابتدا به‌روزرسانی کنیم
        if ($this->isConnected() && 
            (empty($this->accountInfo['username']) || empty($this->accountInfo['first_name']))) {
            $this->updateAccountInfo();
        }
        
        // مطمئن شویم که حداقل اطلاعات پایه‌ای وجود دارد
        $info = $this->accountInfo;
        $info['phone'] = $this->phone;
        $info['connected'] = $this->connected;
        
        return $info;
    }

    /**
     * دریافت لیست چت‌ها (دیالوگ‌ها)
     * 
     * @param int $limit حداکثر تعداد چت‌ها
     * @param int $offsetDate تاریخ شروع جستجو (تاریخ یونیکس)
     * @param int $offsetId شناسه پیام شروع جستجو
     * @param array $offsetPeer کاربر یا گروه شروع جستجو
     * @param bool $excludePinned حذف چت‌های پین شده
     * @param bool $folderId شناسه پوشه
     * @return array لیست چت‌ها
     */
    public function getDialogs(
        int $limit = 100,
        int $offsetDate = 0,
        int $offsetId = 0,
        array $offsetPeer = [],
        bool $excludePinned = false,
        int $folderId = 0
    ): array {
        if (!$this->isConnected()) {
            return [];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // دریافت لیست چت‌ها با پارامترهای پیشرفته
            $dialogs = $MadelineProto->getDialogs([
                'limit' => $limit,
                'offset_date' => $offsetDate,
                'offset_id' => $offsetId,
                'offset_peer' => $offsetPeer,
                'exclude_pinned' => $excludePinned,
                'folder_id' => $folderId
            ]);
            
            // پیش‌پردازش و غنی‌سازی داده‌ها
            $result = [];
            foreach ($dialogs as $peer => $dialog) {
                // اضافه کردن اطلاعات اضافی مفید
                $dialog['id'] = $peer;
                $dialog['title'] = $dialog['title'] ?? $peer;
                
                // اضافه کردن نوع چت (کاربر، گروه، کانال)
                if (isset($dialog['peer_type'])) {
                    $dialog['type'] = $dialog['peer_type'];
                } elseif (isset($dialog['_'])) {
                    $dialog['type'] = strtolower(str_replace('peer', '', $dialog['_']));
                } else {
                    $dialog['type'] = 'unknown';
                }
                
                $result[$peer] = $dialog;
            }
            
            return $result;
            
        } catch (Throwable $e) {
            // در صورت بروز خطا، آرایه خالی برمی‌گردانیم
            return [];
        }
    }

    /**
     * دریافت پیام‌های یک چت
     * 
     * @param string|int $peer شناسه یا نام کاربری چت
     * @param int $limit حداکثر تعداد پیام‌ها
     * @param int $offsetId شناسه پیام شروع جستجو
     * @param int $offsetDate تاریخ شروع جستجو (تاریخ یونیکس)
     * @param int $addOffset تعداد پیام‌های قبل از offsetId که باید نادیده گرفته شوند
     * @param int $maxId حداکثر شناسه پیام
     * @param int $minId حداقل شناسه پیام
     * @param array $reply_to فیلتر برای پیام‌های پاسخ
     * @return array لیست پیام‌ها
     */
    public function getMessages(
        $peer,
        int $limit = 100,
        int $offsetId = 0,
        int $offsetDate = 0,
        int $addOffset = 0,
        int $maxId = 0,
        int $minId = 0,
        array $reply_to = []
    ): array {
        if (!$this->isConnected()) {
            return [];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // دریافت تاریخچه پیام‌ها
            $messages = $MadelineProto->messages->getHistory([
                'peer' => $peer,
                'limit' => $limit,
                'offset_id' => $offsetId,
                'offset_date' => $offsetDate,
                'add_offset' => $addOffset,
                'max_id' => $maxId,
                'min_id' => $minId,
                'hash' => 0,
                'reply_to' => $reply_to
            ]);
            
            // بازگرداندن پیام‌ها
            return $messages['messages'] ?? [];
            
        } catch (RPCErrorException $e) {
            // مدیریت خطاهای خاص RPC
            // می‌توان برخی خطاها را با متد خاص مدیریت کرد
            return [];
        } catch (Throwable $e) {
            // در صورت بروز خطای عمومی، آرایه خالی برمی‌گردانیم
            return [];
        }
    }

    /**
     * ارسال پیام به یک چت
     * 
     * @param string|int $peer شناسه یا نام کاربری چت
     * @param string $message متن پیام
     * @param array $replyTo پاسخ به پیام خاص
     * @return array نتیجه عملیات
     */
    public function sendMessage($peer, string $message, array $replyTo = []): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'status' => 'not_connected',
                'message' => 'حساب کاربری متصل نیست.'
            ];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // ساخت پارامترهای ارسال پیام
            $params = [
                'peer' => $peer,
                'message' => $message,
                'random_id' => random_int(0, PHP_INT_MAX),
            ];
            
            // اضافه کردن پاسخ به پیام خاص اگر موجود باشد
            if (!empty($replyTo)) {
                $params['reply_to'] = $replyTo;
            }
            
            // ارسال پیام
            $result = $MadelineProto->messages->sendMessage($params);
            
            return [
                'success' => true,
                'status' => 'sent',
                'message' => 'پیام با موفقیت ارسال شد.',
                'result' => $result
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e, 'ارسال پیام');
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در ارسال پیام: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ارسال پیام با امکانات پیشرفته (مثل دکمه‌ها، فرمت‌بندی و غیره)
     * 
     * @param string|int $peer شناسه یا نام کاربری چت
     * @param string $message متن پیام
     * @param array $options گزینه‌های اضافی
     * @return array نتیجه عملیات
     */
    public function sendAdvancedMessage($peer, string $message, array $options = []): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'status' => 'not_connected',
                'message' => 'حساب کاربری متصل نیست.'
            ];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // ساخت پارامترهای پایه
            $params = [
                'peer' => $peer,
                'message' => $message,
                'random_id' => random_int(0, PHP_INT_MAX),
            ];
            
            // افزودن گزینه‌های اضافی
            foreach ($options as $key => $value) {
                $params[$key] = $value;
            }
            
            // ارسال پیام
            $result = $MadelineProto->messages->sendMessage($params);
            
            return [
                'success' => true,
                'status' => 'sent',
                'message' => 'پیام پیشرفته با موفقیت ارسال شد.',
                'result' => $result
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e, 'ارسال پیام پیشرفته');
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در ارسال پیام پیشرفته: ' . $e->getMessage()
            ];
        }
    }

    /**
     * استخراج لینک‌های تلگرام از پیام‌های یک چت
     * 
     * @param string|int $peer شناسه یا نام کاربری چت
     * @param int $limit حداکثر تعداد پیام‌ها
     * @param int $offsetId شناسه پیام شروع جستجو
     * @return array لیست لینک‌ها با اطلاعات اضافی
     */
    public function extractLinks($peer, int $limit = 100, int $offsetId = 0): array
    {
        // دریافت پیام‌ها
        $messages = $this->getMessages($peer, $limit, $offsetId);
        
        // آرایه نتیجه با ساختار غنی‌تر
        $result = [];
        
        // متدهای استخراج لینک
        $extractMethods = [
            'extractTelegramInviteLinks',
            'extractTelegramChannelLinks',
            'extractWebsiteLinks'
        ];
        
        // پردازش هر پیام
        foreach ($messages as $message) {
            // اطمینان از اینکه پیام متن دارد
            $text = $message['message'] ?? '';
            if (empty($text) || !is_string($text)) {
                continue;
            }
            
            // اجرای هر متد استخراج
            foreach ($extractMethods as $method) {
                $links = $this->$method($text);
                
                foreach ($links as $link) {
                    $linkKey = md5($link); // کلید یکتا برای هر لینک
                    
                    // اگر لینک قبلاً استخراج نشده، آن را اضافه می‌کنیم
                    if (!isset($result[$linkKey])) {
                        $result[$linkKey] = [
                            'url' => $link,
                            'source' => [
                                'peer' => $peer,
                                'message_id' => $message['id'] ?? 0,
                                'date' => $message['date'] ?? 0,
                            ],
                            'context' => substr($text, 0, 150) . (strlen($text) > 150 ? '...' : ''),
                            'type' => $this->getLinkType($link)
                        ];
                    }
                }
            }
        }
        
        // تبدیل به آرایه ساده
        return array_values($result);
    }

    /**
     * استخراج لینک‌های دعوت تلگرام (t.me/joinchat, t.me/+)
     * 
     * @param string $text متن حاوی لینک‌ها
     * @return array لیست لینک‌های دعوت
     */
    private function extractTelegramInviteLinks(string $text): array
    {
        $links = [];
        
        // الگوهای لینک دعوت
        $patterns = [
            '/https?:\/\/t\.me\/joinchat\/([a-zA-Z0-9_-]+)/i',
            '/https?:\/\/telegram\.me\/joinchat\/([a-zA-Z0-9_-]+)/i',
            '/https?:\/\/t\.me\/\+([a-zA-Z0-9_-]+)/i',
            '/https?:\/\/telegram\.me\/\+([a-zA-Z0-9_-]+)/i'
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
     * استخراج لینک‌های کانال و گروه تلگرام (t.me/username)
     * 
     * @param string $text متن حاوی لینک‌ها
     * @return array لیست لینک‌های کانال/گروه
     */
    private function extractTelegramChannelLinks(string $text): array
    {
        $links = [];
        
        // الگوهای لینک کانال/گروه
        $patterns = [
            '/https?:\/\/t\.me\/([a-zA-Z0-9_]{5,})/i',  // حداقل 5 کاراکتر برای نام کاربری معتبر
            '/https?:\/\/telegram\.me\/([a-zA-Z0-9_]{5,})/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $match) {
                    // حذف لینک‌های سیستمی تلگرام
                    if (!preg_match('/(joinchat|addstickers|addemoji|share|confirmphone)/', $match)) {
                        $links[] = $match;
                    }
                }
            }
        }
        
        return $links;
    }

    /**
     * استخراج لینک‌های وب‌سایت عمومی
     * 
     * @param string $text متن حاوی لینک‌ها
     * @return array لیست لینک‌های وب‌سایت
     */
    private function extractWebsiteLinks(string $text): array
    {
        $links = [];
        
        // الگوی لینک‌های وب‌سایت
        $pattern = '/https?:\/\/(?!t\.me|telegram\.me)([a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]\.)+[a-zA-Z]{2,}(?:\/[^\s()<>]+|\([^\s()<>]+\))*/i';
        
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[0] as $match) {
                $links[] = $match;
            }
        }
        
        return $links;
    }

    /**
     * تعیین نوع لینک (کانال، گروه خصوصی، وب‌سایت)
     * 
     * @param string $link لینک مورد بررسی
     * @return string نوع لینک
     */
    private function getLinkType(string $link): string
    {
        if (stripos($link, 'joinchat') !== false || stripos($link, 't.me/+') !== false) {
            return 'private_group';
        } elseif (stripos($link, 't.me/') !== false || stripos($link, 'telegram.me/') !== false) {
            return 'channel_or_public_group';
        } else {
            return 'website';
        }
    }

    /**
     * پیوستن به یک کانال یا گروه با استفاده از لینک
     * 
     * @param string $link لینک کانال یا گروه
     * @return array نتیجه عملیات
     */
    public function joinByLink(string $link): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'status' => 'not_connected',
                'message' => 'حساب کاربری متصل نیست.'
            ];
        }
        
        try {
            $MadelineProto = $this->getMadelineInstance();
            
            // استخراج کد دعوت یا نام کاربری از لینک
            if (preg_match('/(?:joinchat\/|t\.me\/\+)([a-zA-Z0-9_-]+)/i', $link, $matches)) {
                // لینک خصوصی
                $hash = $matches[1];
                $result = $MadelineProto->messages->importChatInvite(['hash' => $hash]);
            } elseif (preg_match('/(?:t\.me\/|telegram\.me\/)([a-zA-Z0-9_]+)/i', $link, $matches)) {
                // لینک عمومی
                $username = $matches[1];
                $resolved = $MadelineProto->contacts->resolveUsername(['username' => $username]);
                $peer = [
                    '_' => $resolved['_'],
                    'access_hash' => $resolved['access_hash'],
                    'id' => $resolved['id']
                ];
                $result = $MadelineProto->channels->joinChannel(['channel' => $peer]);
            } else {
                return [
                    'success' => false,
                    'status' => 'invalid_link',
                    'message' => 'فرمت لینک نامعتبر است.'
                ];
            }
            
            return [
                'success' => true,
                'status' => 'joined',
                'message' => 'با موفقیت به کانال/گروه پیوستید.',
                'result' => $result
            ];
            
        } catch (RPCErrorException $e) {
            return $this->handleRPCException($e, 'پیوستن به کانال/گروه');
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در پیوستن به کانال/گروه: ' . $e->getMessage()
            ];
        }
    }

    /**
     * مدیریت خطاهای RPC تلگرام
     * 
     * @param RPCErrorException $exception خطای RPC
     * @param string $operation عملیات در حال انجام (برای پیام خطا)
     * @return array پاسخ خطا
     */
    private function handleRPCException(RPCErrorException $exception, string $operation = ''): array
    {
        $message = $exception->getMessage();
        $code = $exception->getCode();
        
        // خطاهای مرتبط با شماره تلفن
        if (stripos($message, 'PHONE_NUMBER_INVALID') !== false) {
            return [
                'success' => false,
                'status' => 'invalid_phone',
                'message' => 'شماره تلفن نامعتبر است. لطفاً با فرمت صحیح وارد کنید (مثال: 989123456789).'
            ];
        }
        
        // خطاهای مرتبط با کد تأیید
        if (stripos($message, 'PHONE_CODE_INVALID') !== false) {
            return [
                'success' => false,
                'status' => 'invalid_code',
                'message' => 'کد تأیید نامعتبر است. لطفاً دوباره تلاش کنید.'
            ];
        }
        if (stripos($message, 'PHONE_CODE_EXPIRED') !== false) {
            return [
                'success' => false,
                'status' => 'code_expired',
                'message' => 'کد تأیید منقضی شده است. لطفاً کد جدیدی درخواست کنید.'
            ];
        }
        
        // خطاهای مرتبط با رمز عبور دو مرحله‌ای
        if (stripos($message, 'PASSWORD_HASH_INVALID') !== false) {
            return [
                'success' => false,
                'status' => 'invalid_password',
                'message' => 'رمز عبور دو مرحله‌ای نادرست است. لطفاً دوباره تلاش کنید.'
            ];
        }
        
        // خطاهای محدودیت (FLOOD)
        if (stripos($message, 'FLOOD_WAIT_') !== false) {
            preg_match('/FLOOD_WAIT_(\d+)/', $message, $matches);
            $waitTime = isset($matches[1]) ? (int)$matches[1] : 60;
            
            return [
                'success' => false,
                'status' => 'flood_wait',
                'message' => "محدودیت درخواست. لطفاً {$waitTime} ثانیه صبر کنید و دوباره تلاش کنید.",
                'wait_time' => $waitTime
            ];
        }
        
        // خطای پیوستن به کانال/گروه
        if (stripos($message, 'INVITE_HASH_EXPIRED') !== false) {
            return [
                'success' => false,
                'status' => 'invite_expired',
                'message' => 'لینک دعوت منقضی شده است.'
            ];
        }
        if (stripos($message, 'CHANNELS_TOO_MUCH') !== false) {
            return [
                'success' => false,
                'status' => 'too_many_channels',
                'message' => 'شما به حداکثر تعداد کانال‌/گروه‌های مجاز پیوسته‌اید.'
            ];
        }
        if (stripos($message, 'INVITE_REQUEST_SENT') !== false) {
            return [
                'success' => true,
                'status' => 'join_requested',
                'message' => 'درخواست پیوستن به گروه ارسال شد و در انتظار تأیید ادمین است.'
            ];
        }
        
        // خطای عمومی
        $operationText = $operation ? " در {$operation}" : '';
        return [
            'success' => false,
            'status' => 'rpc_error',
            'message' => "خطای تلگرام{$operationText}: {$message}",
            'code' => $code,
            'original_message' => $message
        ];
    }

    /**
     * دریافت مسیر فایل جلسه
     * 
     * @return string مسیر کامل فایل جلسه
     */
    private function getSessionPath(): string
    {
        // تبدیل شماره تلفن به یک نام فایل ایمن
        $safePhone = preg_replace('/[^0-9]/', '', $this->phone);
        return self::SESSION_PATH . '/account_' . $safePhone . '.madeline';
    }
}