<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت حساب‌های تلگرام</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/accounts.css">
    <style>
        :root {
            --telegram-primary: #0088cc;
            --telegram-secondary: #6c7883;
            --dark-bg: #212529;
            --card-bg: #282e33;
            --border-color: #343a40;
        }
        
        body {
            background-color: var(--dark-bg);
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .navbar-brand img {
            width: 40px;
            height: 40px;
            margin-left: 10px;
        }
        
        .navbar-dark {
            background-color: var(--card-bg);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .card-header {
            background-color: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border-color);
        }
        
        .btn-telegram {
            background-color: var(--telegram-primary);
            border-color: var(--telegram-primary);
            color: white;
        }
        
        .btn-telegram:hover {
            background-color: #0077b3;
            border-color: #0077b3;
            color: white;
        }
        
        .account-card {
            margin-bottom: 20px;
        }
        
        .account-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--telegram-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .account-details {
            flex-grow: 1;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .status-badge.connected {
            background-color: #198754;
        }
        
        .status-badge.disconnected {
            background-color: #dc3545;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fadeIn {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .card-hover-effect:hover {
            border-color: var(--telegram-primary);
        }
        
        .telegram-icon {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="/assets/img/logo.png" alt="لوگو" width="40" height="40" class="d-inline-block align-text-top">
                <span>لینکدونی تلگرام</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="bi bi-house-door me-1"></i> خانه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/links"><i class="bi bi-link-45deg me-1"></i> لینک‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/channels"><i class="bi bi-megaphone me-1"></i> کانال‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/accounts"><i class="bi bi-person me-1"></i> حساب‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/telegram-desktop"><i class="bi bi-chat-dots me-1"></i> پیام‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/settings"><i class="bi bi-gear me-1"></i> تنظیمات</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a href="#" class="btn btn-sm btn-primary add-account-trigger">
                            <i class="bi bi-person-plus-fill me-1"></i> افزودن اکانت جدید
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container fadeIn">
        <!-- Alerts -->
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i> حساب‌های تلگرام</h5>
                        <a href="/accounts/check-links" class="btn btn-sm btn-primary">
                            <i class="bi bi-search me-1"></i> بررسی لینک‌ها
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($accounts)): ?>
                            <div class="text-center p-4">
                                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#6c7883" class="bi bi-person-x" viewBox="0 0 16 16">
                                  <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0M8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/>
                                  <path d="M8.256 14a4.5 4.5 0 0 1-.229-1.004H3c.001-.246.154-.986.832-1.664C4.484 10.68 5.711 10 8 10q.39 0 .74.025c.226-.341.496-.65.804-.918Q8.844 9.002 8 9c-5 0-6 3-6 4s1 1 1 1z"/>
                                  <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m-.646-4.854.646.647.646-.647a.5.5 0 0 1 .708.708l-.647.646.647.646a.5.5 0 0 1-.708.708l-.646-.647-.646.647a.5.5 0 0 1-.708-.708l.647-.646-.647-.646a.5.5 0 0 1 .708-.708"/>
                                </svg>
                                <h4 class="mt-3 text-muted">هیچ حساب تلگرامی پیدا نشد</h4>
                                <p class="text-muted">برای استفاده از تمام امکانات، حداقل یک حساب تلگرام اضافه کنید.</p>
                                
                                <button class="btn btn-primary mt-3 add-account-trigger">
                                    <i class="bi bi-person-plus-fill me-1"></i> افزودن اکانت تلگرام
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($accounts as $phone => $account): ?>
                                <div class="card account-card card-hover-effect mb-3">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="account-avatar">
                                            <?php echo strtoupper(substr(str_replace('+', '', $phone), -2)); ?>
                                        </div>
                                        <div class="account-details">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">
                                                    <?php echo $account['name'] ?? $phone; ?>
                                                    <?php if (!empty($account['username'])): ?>
                                                        <small class="text-muted">@<?php echo $account['username']; ?></small>
                                                    <?php endif; ?>
                                                </h5>
                                                <span class="badge status-badge <?php echo !empty($account['connected']) ? 'connected' : 'disconnected'; ?>">
                                                    <?php echo !empty($account['connected']) ? 'متصل' : 'قطع اتصال'; ?>
                                                </span>
                                            </div>
                                            <p class="text-muted mb-0"><?php echo $phone; ?></p>
                                            <?php if (!empty($account['last_check'])): ?>
                                                <small class="text-muted">آخرین بررسی: <?php echo date('Y-m-d H:i:s', $account['last_check']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group ms-3">
                                            <?php if (empty($account['connected'])): ?>
                                                <a href="/accounts/connect/<?php echo urlencode($phone); ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-box-arrow-in-right"></i> اتصال
                                                </a>
                                            <?php else: ?>
                                                <a href="/accounts/disconnect/<?php echo urlencode($phone); ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-box-arrow-left"></i> قطع
                                                </a>
                                            <?php endif; ?>
                                            <a href="/accounts/remove/<?php echo urlencode($phone); ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید؟')">
                                                <i class="bi bi-trash"></i> حذف
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-hover-effect">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i> افزودن حساب جدید</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">افزودن حساب تلگرام جدید برای دسترسی به لینک‌های خصوصی و پیام‌های شخصی.</p>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-telegram add-account-trigger">
                                <i class="bi bi-telegram me-2"></i> افزودن حساب تلگرام
                            </button>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>مزیت نسخه جدید:</strong> دیگر نیازی به API ID و API Hash نیست! 
                            <div class="mt-1">این نسخه به صورت خودکار از اطلاعات پیش‌فرض استفاده می‌کند.</div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4 card-hover-effect">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i> راهنما</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="feature-icon me-3 text-primary">
                                <i class="bi bi-shield-lock fs-2"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">امنیت کامل</h6>
                                <p class="text-muted mb-0 small">اطلاعات حساب‌ها به صورت محلی و امن ذخیره می‌شوند.</p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center mb-3">
                            <div class="feature-icon me-3 text-primary">
                                <i class="bi bi-link-45deg fs-2"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">یافتن لینک‌های بیشتر</h6>
                                <p class="text-muted mb-0 small">با اضافه کردن حساب، لینک‌های گروه‌های خصوصی را نیز دریافت کنید.</p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <div class="feature-icon me-3 text-primary">
                                <i class="bi bi-robot fs-2"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">پاسخ خودکار</h6>
                                <p class="text-muted mb-0 small">به پیام‌های خصوصی با استفاده از هوش مصنوعی پاسخ دهید.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="py-4 mt-5 bg-dark">
        <div class="container text-center">
            <p class="mb-0 text-muted">
                <span>لینکدونی تلگرام</span> &copy; <?php echo date('Y'); ?>
                <span class="mx-2">|</span>
                <span>تمامی حقوق محفوظ است</span>
            </p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Include Add Account Modal -->
    <?php include 'add_account_modal.php'; ?>
    
    <script>
        // Show modal when a specific trigger is clicked
        document.addEventListener('DOMContentLoaded', function() {
            const addAccountTriggers = document.querySelectorAll('.add-account-trigger');
            addAccountTriggers.forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const addAccountModal = new bootstrap.Modal(document.getElementById('addAccountModal'));
                    addAccountModal.show();
                });
            });
        });
    </script>
</body>
</html>