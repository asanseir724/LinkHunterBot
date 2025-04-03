<?php

namespace App\Services;

use Exception;

/**
 * Class AccountManager
 * 
 * این کلاس برای مدیریت چندین حساب کاربری تلگرام استفاده می‌شود.
 */
class AccountManager {
    /**
     * مسیر فایل داده
     */
    private $dataFile;
    
    /**
     * لیست حساب‌های کاربری
     */
    private $accounts = [];
    
    /**
     * سازنده کلاس
     * 
     * @param string $dataFile مسیر فایل داده
     */
    public function __construct($dataFile = 'accounts_data.json') {
        $this->dataFile = $dataFile;
        $this->loadData();
    }
    
    /**
     * بارگذاری داده‌ها از فایل
     */
    public function loadData() {
        if (file_exists($this->dataFile)) {
            $data = file_get_contents($this->dataFile);
            $accounts = json_decode($data, true);
            
            if (is_array($accounts)) {
                $this->accounts = $accounts;
            }
        }
    }
    
    /**
     * ذخیره داده‌ها در فایل
     * 
     * @return bool آیا عملیات موفقیت‌آمیز بود؟
     */
    public function saveData() {
        $data = json_encode($this->accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->dataFile, $data) !== false;
    }
    
    /**
     * افزودن حساب کاربری جدید
     * 
     * @param string $phone شماره تلفن
     * @return array نتیجه عملیات
     */
    public function addAccount($phone) {
        // اطمینان از فرمت صحیح شماره تلفن
        $phone = $this->normalizePhone($phone);
        
        if (empty($phone)) {
            return [
                'success' => false,
                'message' => 'شماره تلفن نامعتبر است.'
            ];
        }
        
        // بررسی تکراری نبودن حساب
        if ($this->accountExists($phone)) {
            return [
                'success' => false,
                'message' => 'این حساب کاربری قبلاً اضافه شده است.'
            ];
        }
        
        // ایجاد حساب کاربری جدید
        $accountInfo = [
            'phone' => $phone,
            'first_name' => '',
            'last_name' => '',
            'username' => '',
            'connected' => false,
            'added_time' => time(),
            'last_check_time' => null
        ];
        
        // ذخیره حساب در آرایه
        $this->accounts[$phone] = $accountInfo;
        
        // ذخیره در فایل
        if ($this->saveData()) {
            return [
                'success' => true,
                'message' => 'حساب کاربری با موفقیت اضافه شد.',
                'account' => $accountInfo
            ];
        }
        
        return [
            'success' => false,
            'message' => 'خطا در ذخیره اطلاعات حساب کاربری.'
        ];
    }
    
    /**
     * حذف حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return array نتیجه عملیات
     */
    public function removeAccount($phone) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'message' => 'این حساب کاربری وجود ندارد.'
            ];
        }
        
        // قطع اتصال حساب کاربری قبل از حذف
        if ($this->isConnected($phone)) {
            $userAccount = $this->getUserAccount($phone);
            $userAccount->disconnect();
        }
        
        // حذف از آرایه
        unset($this->accounts[$phone]);
        
        // ذخیره در فایل
        if ($this->saveData()) {
            return [
                'success' => true,
                'message' => 'حساب کاربری با موفقیت حذف شد.'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'خطا در حذف حساب کاربری.'
        ];
    }
    
    /**
     * اتصال به حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return array نتیجه عملیات
     */
    public function connectAccount($phone) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'message' => 'این حساب کاربری وجود ندارد.'
            ];
        }
        
        // ایجاد نمونه حساب کاربری
        $userAccount = $this->getUserAccount($phone);
        
        // بررسی وضعیت اتصال فعلی
        if ($userAccount->isConnected()) {
            return [
                'success' => true,
                'message' => 'این حساب کاربری در حال حاضر متصل است.'
            ];
        }
        
        // تلاش برای اتصال
        $result = $userAccount->connect();
        
        // به‌روزرسانی وضعیت اتصال در دیتابیس
        if ($result['success'] || $result['status'] === 'code_needed' || $result['status'] === '2fa_needed') {
            $this->accounts[$phone]['connected'] = $result['success'];
            $this->saveData();
        }
        
        return $result;
    }
    
    /**
     * تأیید کد احراز هویت
     * 
     * @param string $phone شماره تلفن
     * @param string $code کد تأیید
     * @return array نتیجه عملیات
     */
    public function verifyCode($phone, $code) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'message' => 'این حساب کاربری وجود ندارد.'
            ];
        }
        
        // ایجاد نمونه حساب کاربری
        $userAccount = $this->getUserAccount($phone);
        
        // تلاش برای تأیید کد
        $result = $userAccount->verifyCode($code, '');
        
        // به‌روزرسانی وضعیت اتصال و اطلاعات در دیتابیس
        if ($result['success']) {
            $accountInfo = $userAccount->getAccountInfo();
            $this->accounts[$phone] = array_merge($this->accounts[$phone], $accountInfo);
            $this->saveData();
        }
        
        return $result;
    }
    
    /**
     * تأیید رمز عبور دو مرحله‌ای
     * 
     * @param string $phone شماره تلفن
     * @param string $password رمز عبور
     * @return array نتیجه عملیات
     */
    public function verify2FA($phone, $password) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'message' => 'این حساب کاربری وجود ندارد.'
            ];
        }
        
        // ایجاد نمونه حساب کاربری
        $userAccount = $this->getUserAccount($phone);
        
        // تلاش برای تأیید رمز عبور
        $result = $userAccount->verify2FA($password);
        
        // به‌روزرسانی وضعیت اتصال و اطلاعات در دیتابیس
        if ($result['success']) {
            $accountInfo = $userAccount->getAccountInfo();
            $this->accounts[$phone] = array_merge($this->accounts[$phone], $accountInfo);
            $this->saveData();
        }
        
        return $result;
    }
    
    /**
     * قطع اتصال حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return array نتیجه عملیات
     */
    public function disconnectAccount($phone) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return [
                'success' => false,
                'message' => 'این حساب کاربری وجود ندارد.'
            ];
        }
        
        // ایجاد نمونه حساب کاربری
        $userAccount = $this->getUserAccount($phone);
        
        // تلاش برای قطع اتصال
        $result = $userAccount->disconnect();
        
        // به‌روزرسانی وضعیت اتصال در دیتابیس
        if ($result['success']) {
            $this->accounts[$phone]['connected'] = false;
            $this->saveData();
        }
        
        return $result;
    }
    
    /**
     * بررسی وجود حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return bool آیا حساب وجود دارد؟
     */
    public function accountExists($phone) {
        $phone = $this->normalizePhone($phone);
        return isset($this->accounts[$phone]);
    }
    
    /**
     * بررسی وضعیت اتصال حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return bool آیا حساب متصل است؟
     */
    public function isConnected($phone) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return false;
        }
        
        // بررسی وضعیت اتصال واقعی
        $userAccount = $this->getUserAccount($phone);
        $connected = $userAccount->isConnected();
        
        // به‌روزرسانی وضعیت در دیتابیس اگر تغییر کرده باشد
        if ($connected !== $this->accounts[$phone]['connected']) {
            $this->accounts[$phone]['connected'] = $connected;
            $this->saveData();
        }
        
        return $connected;
    }
    
    /**
     * دریافت اطلاعات یک حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return array|null اطلاعات حساب یا null
     */
    public function getAccount($phone) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return null;
        }
        
        return $this->accounts[$phone];
    }
    
    /**
     * دریافت لیست تمام حساب‌های کاربری
     * 
     * @return array لیست حساب‌ها
     */
    public function getAllAccounts() {
        return $this->accounts;
    }
    
    /**
     * دریافت لیست حساب‌های متصل
     * 
     * @param bool $checkRealStatus بررسی وضعیت واقعی اتصال
     * @return array لیست حساب‌های متصل
     */
    public function getConnectedAccounts($checkRealStatus = false) {
        $connectedAccounts = [];
        
        foreach ($this->accounts as $phone => $account) {
            $connected = $account['connected'];
            
            if ($checkRealStatus) {
                // بررسی وضعیت واقعی اتصال
                $userAccount = $this->getUserAccount($phone);
                $connected = $userAccount->isConnected();
                
                // به‌روزرسانی وضعیت در دیتابیس اگر تغییر کرده باشد
                if ($connected !== $account['connected']) {
                    $this->accounts[$phone]['connected'] = $connected;
                    $this->saveData();
                }
            }
            
            if ($connected) {
                $connectedAccounts[$phone] = $account;
            }
        }
        
        return $connectedAccounts;
    }
    
    /**
     * به‌روزرسانی زمان آخرین بررسی حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return bool آیا به‌روزرسانی موفقیت‌آمیز بود؟
     */
    public function updateLastCheckTime($phone) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            return false;
        }
        
        $this->accounts[$phone]['last_check_time'] = time();
        return $this->saveData();
    }
    
    /**
     * استخراج لینک‌ها از چت‌های حساب‌های کاربری متصل
     * 
     * @param LinkManager $linkManager مدیریت کننده لینک‌ها
     * @param int $limit حداکثر تعداد پیام‌ها در هر چت
     * @return array آمار استخراج لینک‌ها
     */
    public function extractLinksFromAccounts($linkManager, $limit = 100) {
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
                $userAccount = $this->getUserAccount($phone);
                
                // دریافت لیست چت‌ها
                $dialogs = $userAccount->getDialogs();
                
                foreach ($dialogs as $peer => $dialog) {
                    // استخراج لینک‌ها از چت
                    $links = $userAccount->extractLinksFromChat($peer, $limit);
                    
                    // افزودن لینک‌ها به مدیریت کننده لینک‌ها
                    foreach ($links as $link) {
                        $isNew = $linkManager->addLink($link, "اکانت: {$phone}", "استخراج شده از چت");
                        
                        if ($isNew) {
                            $stats['new_links']++;
                        }
                        
                        $stats['total_links']++;
                    }
                }
                
                // به‌روزرسانی زمان آخرین بررسی
                $this->updateLastCheckTime($phone);
                $stats['processed_accounts']++;
            } catch (Exception $e) {
                $stats['errors'][] = "خطا در بررسی حساب {$phone}: " . $e->getMessage();
            }
        }
        
        return $stats;
    }
    
    /**
     * دریافت نمونه حساب کاربری
     * 
     * @param string $phone شماره تلفن
     * @return UserAccount نمونه حساب کاربری
     */
    private function getUserAccount($phone) {
        $phone = $this->normalizePhone($phone);
        
        if (!$this->accountExists($phone)) {
            throw new Exception("حساب کاربری با شماره {$phone} وجود ندارد.");
        }
        
        return new UserAccount($phone, $this->accounts[$phone]);
    }
    
    /**
     * نرمال‌سازی شماره تلفن
     * 
     * @param string $phone شماره تلفن
     * @return string شماره تلفن نرمال شده
     */
    private function normalizePhone($phone) {
        // حذف فاصله‌ها و کاراکترهای اضافی
        $phone = preg_replace('/\s+/', '', $phone);
        
        // حذف + از ابتدای شماره
        if (substr($phone, 0, 1) === '+') {
            $phone = substr($phone, 1);
        }
        
        // بررسی معتبر بودن شماره
        if (!preg_match('/^\d{10,15}$/', $phone)) {
            return '';
        }
        
        return $phone;
    }
}