<?php

namespace App\Services;

use Throwable;

/**
 * کلاس مدیریت حساب‌های کاربری تلگرام
 * 
 * این کلاس برای مدیریت چندین حساب کاربری تلگرام استفاده می‌شود و
 * با کلاس UserAccount کار می‌کند که از MadelineProto استفاده می‌کند.
 */
class AccountManager
{
    /**
     * مسیر فایل ذخیره‌سازی داده‌ها
     */
    private string $dataFile;
    
    /**
     * آرایه حساب‌های کاربری
     */
    private array $accounts = [];
    
    /**
     * API ID تلگرام
     */
    private ?int $apiId;
    
    /**
     * API Hash تلگرام
     */
    private ?string $apiHash;
    
    /**
     * سازنده کلاس
     * 
     * @param string $dataFile مسیر فایل ذخیره‌سازی داده‌ها
     * @param int|null $apiId API ID تلگرام (اختیاری، از متغیرهای محیطی استفاده می‌کند)
     * @param string|null $apiHash API Hash تلگرام (اختیاری، از متغیرهای محیطی استفاده می‌کند)
     */
    public function __construct(
        string $dataFile = 'accounts_data.json',
        ?int $apiId = null,
        ?string $apiHash = null
    ) {
        $this->dataFile = $dataFile;
        $this->apiId = $apiId ?? (int)($_ENV['TELEGRAM_API_ID'] ?? 0);
        $this->apiHash = $apiHash ?? ($_ENV['TELEGRAM_API_HASH'] ?? '');
        
        $this->loadData();
    }
    
    /**
     * بارگذاری داده‌ها از فایل
     * 
     * @return bool آیا بارگذاری موفقیت‌آمیز بود؟
     */
    public function loadData(): bool
    {
        if (file_exists($this->dataFile)) {
            try {
                $data = file_get_contents($this->dataFile);
                $accounts = json_decode($data, true);
                
                if (is_array($accounts)) {
                    $this->accounts = $accounts;
                    return true;
                }
            } catch (Throwable $e) {
                // در صورت خطا، از آرایه خالی استفاده می‌کنیم
            }
        }
        
        $this->accounts = [];
        return false;
    }
    
    /**
     * ذخیره داده‌ها در فایل
     * 
     * @return bool آیا ذخیره‌سازی موفقیت‌آمیز بود؟
     */
    public function saveData(): bool
    {
        try {
            $data = json_encode($this->accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return file_put_contents($this->dataFile, $data) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
    
    /**
     * افزودن حساب کاربری جدید
     * 
     * @param string $phone شماره تلفن
     * @return array نتیجه عملیات
     */
    public function addAccount(string $phone): array
    {
        // نرمال‌سازی شماره تلفن
        $phone = $this->normalizePhone($phone);
        
        // بررسی معتبر بودن شماره تلفن
        if (empty($phone)) {
            return [
                'success' => false,
                'status' => 'invalid_phone',
                'message' => 'شماره تلفن نامعتبر است. لطفاً با فرمت صحیح وارد کنید (مثال: 989123456789).'
            ];
        }
        
        // بررسی تکراری نبودن حساب
        if ($this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'already_exists',
                'message' => 'این حساب کاربری قبلاً اضافه شده است.'
            ];
        }
        
        // ایجاد اطلاعات اولیه حساب
        $accountInfo = [
            'phone' => $phone,
            'first_name' => '',
            'last_name' => '',
            'username' => '',
            'user_id' => '',
            'connected' => false,
            'added_time' => time(),
            'last_check_time' => null
        ];
        
        // ذخیره در آرایه حساب‌ها
        $this->accounts[$phone] = $accountInfo;
        
        // ذخیره در فایل
        if ($this->saveData()) {
            return [
                'success' => true,
                'status' => 'added',
                'message' => 'حساب کاربری با موفقیت اضافه شد.',
                'account' => $accountInfo
            ];
        }
        
        // حذف از آرایه در صورت خطا در ذخیره‌سازی
        unset($this->accounts[$phone]);
        
        return [
            'success' => false,
            'status' => 'save_error',
            'message' => 'خطا در ذخیره اطلاعات حساب کاربری.'
        ];
    }
    
    /**
     * شروع فرآیند اتصال به حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return array نتیجه عملیات
     */
    public function startLoginProcess(string $phone): array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
            ];
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // بررسی وضعیت اتصال فعلی
            if ($userAccount->isConnected()) {
                // به‌روزرسانی اطلاعات حساب کاربری
                $userAccount->updateAccountInfo();
                $accountInfo = $userAccount->getAccountInfo();
                $this->accounts[$phone] = array_merge($this->accounts[$phone], $accountInfo);
                $this->saveData();
                
                return [
                    'success' => true,
                    'status' => 'already_connected',
                    'message' => 'این حساب کاربری در حال حاضر متصل است.'
                ];
            }
            
            // شروع فرآیند ورود
            $result = $userAccount->startLogin();
            
            // ذخیره وضعیت اتصال
            if ($result['success']) {
                $accountInfo = $userAccount->getAccountInfo();
                $this->accounts[$phone] = array_merge($this->accounts[$phone], $accountInfo);
                $this->saveData();
            }
            
            return $result;
            
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
     * @param string $phone شماره تلفن
     * @param string $code کد تأیید
     * @param string $phoneCodeHash هش کد تلفن (اختیاری)
     * @return array نتیجه عملیات
     */
    public function submitCode(string $phone, string $code, string $phoneCodeHash = ''): array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
            ];
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // ارسال کد تأیید
            $result = $userAccount->submitCode($code, $phoneCodeHash);
            
            // ذخیره وضعیت و اطلاعات حساب کاربری
            if ($result['success']) {
                $accountInfo = $userAccount->getAccountInfo();
                $this->accounts[$phone] = array_merge($this->accounts[$phone], $accountInfo);
                $this->saveData();
            }
            
            return $result;
            
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
     * @param string $phone شماره تلفن
     * @param string $password رمز عبور
     * @return array نتیجه عملیات
     */
    public function submit2FA(string $phone, string $password): array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
            ];
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // ارسال رمز عبور دو مرحله‌ای
            $result = $userAccount->submit2FA($password);
            
            // ذخیره وضعیت و اطلاعات حساب کاربری
            if ($result['success']) {
                $accountInfo = $userAccount->getAccountInfo();
                $this->accounts[$phone] = array_merge($this->accounts[$phone], $accountInfo);
                $this->saveData();
            }
            
            return $result;
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در تأیید رمز عبور دو مرحله‌ای: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * درخواست بازیابی حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @param string $emailPattern الگوی ایمیل بازیابی (اختیاری)
     * @return array نتیجه عملیات
     */
    public function requestRecovery(string $phone, string $emailPattern = ''): array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
            ];
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // درخواست بازیابی
            return $userAccount->requestRecovery($emailPattern);
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در درخواست بازیابی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * خروج از حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @param bool $deleteSession آیا فایل جلسه حذف شود؟
     * @return array نتیجه عملیات
     */
    public function logout(string $phone, bool $deleteSession = true): array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
            ];
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // خروج از حساب کاربری
            $result = $userAccount->logout($deleteSession);
            
            // به‌روزرسانی وضعیت اتصال
            if ($result['success']) {
                $this->accounts[$phone]['connected'] = false;
                $this->saveData();
            }
            
            return $result;
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در خروج از حساب کاربری: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * حذف حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @param bool $logout آیا ابتدا از حساب کاربری خارج شود؟
     * @return array نتیجه عملیات
     */
    public function removeAccount(string $phone, bool $logout = true): array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
            ];
        }
        
        // اگر کاربر متصل است، ابتدا خروج انجام شود
        if ($logout && $this->isConnected($phone)) {
            $this->logout($phone, true);
        }
        
        // حذف از آرایه حساب‌ها
        unset($this->accounts[$phone]);
        
        // ذخیره تغییرات
        if ($this->saveData()) {
            return [
                'success' => true,
                'status' => 'removed',
                'message' => 'حساب کاربری با موفقیت حذف شد.'
            ];
        }
        
        // بازگرداندن حساب در صورت خطا در ذخیره‌سازی
        return [
            'success' => false,
            'status' => 'save_error',
            'message' => 'خطا در حذف حساب کاربری.'
        ];
    }
    
    /**
     * بررسی وضعیت اتصال حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return bool آیا حساب متصل است؟
     */
    public function isConnected(string $phone): bool
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return false;
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // بررسی وضعیت اتصال واقعی
            $connected = $userAccount->isConnected();
            
            // به‌روزرسانی وضعیت اتصال در آرایه حساب‌ها اگر تغییر کرده باشد
            if ($connected !== $this->accounts[$phone]['connected']) {
                $this->accounts[$phone]['connected'] = $connected;
                $this->saveData();
            }
            
            return $connected;
            
        } catch (Throwable $e) {
            // در صورت بروز خطا، فرض می‌کنیم اتصال برقرار نیست
            $this->accounts[$phone]['connected'] = false;
            $this->saveData();
            return false;
        }
    }
    
    /**
     * دریافت اطلاعات حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @param bool $forceUpdate آیا اطلاعات از سرور به‌روزرسانی شود؟
     * @return array|null اطلاعات حساب کاربری یا null در صورت عدم وجود
     */
    public function getAccount(string $phone, bool $forceUpdate = false): ?array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return null;
        }
        
        // به‌روزرسانی اطلاعات از سرور اگر درخواست شده باشد
        if ($forceUpdate && $this->isConnected($phone)) {
            try {
                $userAccount = $this->createUserAccount($phone);
                $userAccount->updateAccountInfo();
                $accountInfo = $userAccount->getAccountInfo();
                $this->accounts[$phone] = array_merge($this->accounts[$phone], $accountInfo);
                $this->saveData();
            } catch (Throwable $e) {
                // اگر خطایی رخ داد، از داده‌های محلی استفاده می‌کنیم
            }
        }
        
        return $this->accounts[$phone];
    }
    
    /**
     * دریافت لیست تمام حساب‌های کاربری
     * 
     * @param bool $checkRealStatus آیا وضعیت اتصال واقعی بررسی شود؟
     * @return array لیست حساب‌های کاربری
     */
    public function getAllAccounts(bool $checkRealStatus = false): array
    {
        if (!$checkRealStatus) {
            return $this->accounts;
        }
        
        // به‌روزرسانی وضعیت اتصال واقعی تمام حساب‌ها
        foreach ($this->accounts as $phone => $account) {
            $this->isConnected($phone);
        }
        
        return $this->accounts;
    }
    
    /**
     * دریافت لیست حساب‌های متصل
     * 
     * @param bool $checkRealStatus آیا وضعیت اتصال واقعی بررسی شود؟
     * @return array لیست حساب‌های متصل
     */
    public function getConnectedAccounts(bool $checkRealStatus = false): array
    {
        $connectedAccounts = [];
        
        foreach ($this->accounts as $phone => $account) {
            $connected = $account['connected'];
            
            // بررسی وضعیت اتصال واقعی اگر درخواست شده باشد
            if ($checkRealStatus) {
                $connected = $this->isConnected($phone);
            }
            
            if ($connected) {
                $connectedAccounts[$phone] = $account;
            }
        }
        
        return $connectedAccounts;
    }
    
    /**
     * استخراج لینک‌ها از چت‌های حساب‌های کاربری متصل
     * 
     * @param LinkManager $linkManager مدیریت کننده لینک‌ها
     * @param int $limit حداکثر تعداد پیام‌ها در هر چت
     * @return array آمار استخراج لینک‌ها
     */
    public function extractLinksFromAccounts(LinkManager $linkManager, int $limit = 100): array
    {
        $stats = [
            'total_accounts' => 0,
            'processed_accounts' => 0,
            'total_links' => 0,
            'new_links' => 0,
            'errors' => []
        ];
        
        // دریافت حساب‌های متصل
        $connectedAccounts = $this->getConnectedAccounts(true);
        $stats['total_accounts'] = count($connectedAccounts);
        
        foreach ($connectedAccounts as $phone => $account) {
            try {
                // ایجاد نمونه حساب کاربری
                $userAccount = $this->createUserAccount($phone);
                
                // دریافت لیست چت‌ها
                $dialogs = $userAccount->getDialogs();
                
                foreach ($dialogs as $peer => $dialog) {
                    // استخراج لینک‌ها از چت
                    $extractedLinks = $userAccount->extractLinks($peer, $limit);
                    
                    // افزودن لینک‌ها به مدیریت کننده لینک‌ها
                    foreach ($extractedLinks as $linkInfo) {
                        $isNew = $linkManager->addLink(
                            $linkInfo['url'],
                            "اکانت: {$phone}",
                            $linkInfo['context'] ?? 'استخراج شده از چت'
                        );
                        
                        if ($isNew) {
                            $stats['new_links']++;
                        }
                        
                        $stats['total_links']++;
                    }
                }
                
                // به‌روزرسانی زمان آخرین بررسی
                $this->updateLastCheckTime($phone);
                $stats['processed_accounts']++;
                
            } catch (Throwable $e) {
                $stats['errors'][] = "خطا در بررسی حساب {$phone}: " . $e->getMessage();
            }
        }
        
        return $stats;
    }
    
    /**
     * پیوستن به یک گروه یا کانال با استفاده از لینک
     * 
     * @param string $link لینک گروه یا کانال
     * @param string $phone شماره تلفن حساب کاربری (اگر خالی باشد، از اولین حساب متصل استفاده می‌شود)
     * @return array نتیجه عملیات
     */
    public function joinByLink(string $link, string $phone = ''): array
    {
        // اگر شماره تلفن مشخص نشده، از اولین حساب متصل استفاده می‌کنیم
        if (empty($phone)) {
            $connectedAccounts = $this->getConnectedAccounts(true);
            
            if (empty($connectedAccounts)) {
                return [
                    'success' => false,
                    'status' => 'no_account',
                    'message' => 'هیچ حساب متصلی برای پیوستن به گروه/کانال وجود ندارد.'
                ];
            }
            
            // استفاده از اولین حساب متصل
            $phone = array_key_first($connectedAccounts);
        } else {
            $phone = $this->normalizePhone($phone);
            
            // بررسی وجود و اتصال حساب
            if (!$this->accountExists($phone)) {
                return [
                    'success' => false,
                    'status' => 'not_found',
                    'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
                ];
            }
            
            if (!$this->isConnected($phone)) {
                return [
                    'success' => false,
                    'status' => 'not_connected',
                    'message' => 'حساب کاربری متصل نیست.'
                ];
            }
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // پیوستن به گروه/کانال
            $result = $userAccount->joinByLink($link);
            
            return $result;
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در پیوستن به گروه/کانال: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * به‌روزرسانی زمان آخرین بررسی حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return bool آیا به‌روزرسانی موفقیت‌آمیز بود؟
     */
    public function updateLastCheckTime(string $phone): bool
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود حساب
        if (!$this->accountExists($phone)) {
            return false;
        }
        
        // به‌روزرسانی زمان آخرین بررسی
        $this->accounts[$phone]['last_check_time'] = time();
        return $this->saveData();
    }
    
    /**
     * ارسال پیام به یک کاربر یا گروه
     * 
     * @param string $phone شماره تلفن حساب فرستنده
     * @param string $peer شناسه یا نام کاربری گیرنده
     * @param string $message متن پیام
     * @param array $options گزینه‌های اضافی
     * @return array نتیجه عملیات
     */
    public function sendMessage(string $phone, string $peer, string $message, array $options = []): array
    {
        $phone = $this->normalizePhone($phone);
        
        // بررسی وجود و اتصال حساب
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'حساب کاربری با این شماره تلفن یافت نشد.'
            ];
        }
        
        if (!$this->isConnected($phone)) {
            return [
                'success' => false,
                'status' => 'not_connected',
                'message' => 'حساب کاربری متصل نیست.'
            ];
        }
        
        try {
            // ایجاد نمونه حساب کاربری
            $userAccount = $this->createUserAccount($phone);
            
            // ارسال پیام ساده یا پیشرفته
            if (empty($options)) {
                $result = $userAccount->sendMessage($peer, $message);
            } else {
                $result = $userAccount->sendAdvancedMessage($peer, $message, $options);
            }
            
            return $result;
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در ارسال پیام: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ایجاد نمونه حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return UserAccount نمونه حساب کاربری
     * @throws \Exception در صورت عدم وجود حساب کاربری
     */
    public function createUserAccount(string $phone): UserAccount
    {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            throw new \Exception("حساب کاربری با شماره {$phone} وجود ندارد.");
        }
        
        return new UserAccount(
            $phone,
            $this->accounts[$phone],
            $this->apiId,
            $this->apiHash
        );
    }
    
    /**
     * بررسی وجود حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return bool آیا حساب وجود دارد؟
     */
    public function accountExists(string $phone): bool
    {
        $phone = $this->normalizePhone($phone);
        return !empty($phone) && isset($this->accounts[$phone]);
    }
    
    /**
     * نرمال‌سازی شماره تلفن
     * 
     * حذف کاراکترهای اضافی و فرمت‌بندی شماره تلفن
     * 
     * @param string $phone شماره تلفن
     * @return string شماره تلفن نرمال‌شده یا رشته خالی در صورت نامعتبر بودن
     */
    private function normalizePhone(string $phone): string
    {
        // حذف فاصله‌ها و کاراکترهای اضافی
        $phone = preg_replace('/\s+/', '', $phone);
        
        // حذف + از ابتدای شماره
        $phone = ltrim($phone, '+');
        
        // اطمینان از معتبر بودن شماره (فقط شامل 10-15 رقم)
        if (!preg_match('/^\d{10,15}$/', $phone)) {
            return '';
        }
        
        return $phone;
    }
}