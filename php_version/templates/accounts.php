<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت حساب‌های کاربری تلگرام</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .telegram-color {
            background-color: #0088cc;
            color: white;
        }
        .telegram-color:hover {
            background-color: #0077b3;
            color: white;
        }
        .account-row {
            transition: all 0.3s ease;
        }
        .account-row:hover {
            background-color: #f8f9fa;
        }
        .account-photo {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        .account-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #6c757d;
        }
        .badge-connected {
            background-color: #198754;
        }
        .badge-disconnected {
            background-color: #dc3545;
        }
        .actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">
                <i class="bi bi-person-badge me-2"></i>
                مدیریت حساب‌های کاربری تلگرام
            </h1>
            <div>
                <a href="/telegram-desktop" class="btn btn-outline-primary me-2">
                    <i class="bi bi-chat-left-text me-1"></i>
                    رابط پیام‌های خصوصی
                </a>
                <a href="/" class="btn btn-outline-secondary">
                    <i class="bi bi-house me-1"></i>
                    بازگشت به خانه
                </a>
            </div>
        </div>
        
        <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header telegram-color d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i> حساب‌های کاربری</h5>
                        <a href="/accounts/check-links" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-link-45deg me-1"></i>
                            بررسی لینک‌ها در همه حساب‌ها
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($accounts)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                هنوز هیچ حساب کاربری اضافه نشده است. لطفاً از فرم سمت راست یک حساب جدید اضافه کنید.
                            </p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="border-0">حساب کاربری</th>
                                        <th class="border-0">وضعیت</th>
                                        <th class="border-0">آخرین بررسی</th>
                                        <th class="border-0">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($accounts as $account): ?>
                                    <tr class="account-row">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($account['photo'])): ?>
                                                <img src="<?php echo $account['photo']; ?>" alt="عکس پروفایل" class="account-photo me-3">
                                                <?php else: ?>
                                                <div class="account-placeholder me-3">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0">
                                                        <?php echo !empty($account['first_name']) ? 
                                                            $account['first_name'] . ' ' . ($account['last_name'] ?? '') : 
                                                            $account['phone']; ?>
                                                    </h6>
                                                    <div class="small text-muted">
                                                        <?php if (!empty($account['username'])): ?>
                                                        <span class="me-2">@<?php echo $account['username']; ?></span>
                                                        <?php endif; ?>
                                                        <span><?php echo $account['phone']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($account['connected']): ?>
                                            <span class="badge badge-connected">متصل</span>
                                            <?php else: ?>
                                            <span class="badge badge-disconnected">غیرمتصل</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($account['last_check_time'])): ?>
                                            <small><?php echo date('Y/m/d H:i', $account['last_check_time']); ?></small>
                                            <?php else: ?>
                                            <small class="text-muted">بررسی نشده</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <?php if ($account['connected']): ?>
                                            <a href="/accounts/disconnect/<?php echo urlencode($account['phone']); ?>" class="btn btn-outline-danger btn-sm" title="قطع اتصال">
                                                <i class="bi bi-box-arrow-left"></i>
                                            </a>
                                            <?php else: ?>
                                            <a href="/accounts/connect/<?php echo urlencode($account['phone']); ?>" class="btn btn-outline-success btn-sm" title="اتصال">
                                                <i class="bi bi-box-arrow-in-right"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <a href="/accounts/remove/<?php echo urlencode($account['phone']); ?>" class="btn btn-outline-danger btn-sm" title="حذف" 
                                               onclick="return confirm('آیا از حذف این حساب کاربری اطمینان دارید؟');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header telegram-color">
                        <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i> افزودن حساب جدید</h5>
                    </div>
                    <div class="card-body">
                        <form action="/accounts/add" method="post">
                            <div class="mb-3">
                                <label for="phone" class="form-label">شماره تلفن (با فرمت بین‌المللی)</label>
                                <div class="input-group">
                                    <span class="input-group-text">+</span>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="989123456789" required pattern="[0-9]{10,15}">
                                </div>
                                <div class="form-text">شماره تلفن را بدون فاصله و با کد کشور وارد کنید (مثلا: 989123456789)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="api_id" class="form-label">API ID</label>
                                <input type="number" class="form-control" id="api_id" name="api_id" placeholder="2040" required>
                                <div class="form-text">API ID را از <a href="https://my.telegram.org/apps" target="_blank">my.telegram.org/apps</a> دریافت کنید</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="api_hash" class="form-label">API Hash</label>
                                <input type="text" class="form-control" id="api_hash" name="api_hash" placeholder="b13441a1f607e10a989891a5462e627" required>
                                <div class="form-text">API Hash را از <a href="https://my.telegram.org/apps" target="_blank">my.telegram.org/apps</a> دریافت کنید</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">نام (اختیاری)</label>
                                <input type="text" class="form-control" id="name" name="name" placeholder="اکانت اصلی">
                                <div class="form-text">نامی برای شناسایی راحت‌تر این اکانت</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn telegram-color">
                                    <i class="bi bi-plus-circle me-1"></i>
                                    افزودن اکانت
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i> راهنما</h5>
                    </div>
                    <div class="card-body">
                        <h6>نحوه افزودن حساب:</h6>
                        <ol class="ps-3">
                            <li>شماره تلفن را در فرم مقابل وارد کنید.</li>
                            <li>دکمه افزودن حساب را بزنید.</li>
                            <li>کد تأیید ارسال شده به تلگرام خود را وارد کنید.</li>
                            <li>اگر حساب شما دارای احراز هویت دو مرحله‌ای است، رمز عبور را نیز وارد کنید.</li>
                        </ol>
                        
                        <h6 class="mt-3">نکات مهم:</h6>
                        <ul class="ps-3">
                            <li>برای استفاده از قابلیت بررسی لینک‌ها، باید حساب شما متصل باشد.</li>
                            <li>پس از اتصال حساب، می‌توانید پیام‌های خصوصی را از طریق دکمه «رابط پیام‌های خصوصی» مشاهده کنید.</li>
                            <li>می‌توانید از چندین حساب همزمان استفاده کنید تا محدودیت‌های تلگرام را دور بزنید.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // پنهان کردن پیام‌های اطلاع‌رسانی بعد از 5 ثانیه
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>