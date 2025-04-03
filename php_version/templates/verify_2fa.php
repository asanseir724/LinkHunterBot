<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأیید رمز عبور تلگرام</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            animation: fadeIn 0.5s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card-header {
            background-color: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border-color);
        }
        
        .telegram-color {
            background-color: var(--telegram-primary);
            color: white;
        }
        
        .form-control {
            background-color: #343a40;
            border-color: #495057;
            color: #fff;
        }
        
        .form-control:focus {
            background-color: #2b3035;
            border-color: var(--telegram-primary);
            box-shadow: 0 0 0 0.25rem rgba(0, 136, 204, 0.25);
            color: #fff;
        }
        
        .input-group-text {
            background-color: #495057;
            border-color: #495057;
            color: #fff;
        }
        
        .form-text {
            color: #adb5bd;
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
        
        .auth-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--telegram-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
        
        .password-hint {
            background-color: rgba(0, 136, 204, 0.1);
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 15px;
            border-left: 4px solid var(--telegram-primary);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="text-center mb-4">
                    <a href="/" class="text-decoration-none text-white">
                        <h2><i class="bi bi-telegram me-2"></i> لینکدونی تلگرام</h2>
                    </a>
                    <p class="text-muted">سیستم مدیریت لینک‌های تلگرام</p>
                </div>
                
                <div class="card">
                    <div class="card-header telegram-color text-center">
                        <h3><i class="bi bi-shield-lock me-2"></i> تأیید دو مرحله‌ای تلگرام</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mb-4">
                            <div class="auth-icon">
                                <i class="bi bi-lock"></i>
                            </div>
                            <p class="lead">
                                حساب شما نیاز به تأیید رمز عبور دارد
                            </p>
                            <p class="text-muted">
                                لطفاً رمز عبور دو مرحله‌ای تنظیم شده برای حساب <?php echo $phone; ?> را وارد کنید.
                            </p>
                        </div>
                        
                        <form action="/accounts/verify-2fa" method="post" autocomplete="off">
                            <input type="hidden" name="phone" value="<?php echo $phone; ?>">
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">رمز عبور دو مرحله‌ای</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="رمز عبور دو مرحله‌ای" required autofocus>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                
                                <?php if (!empty($hint)): ?>
                                <div class="password-hint mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>راهنمای رمز:</strong> <?php echo $hint; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-telegram btn-lg">
                                    <i class="bi bi-check-circle me-2"></i> تأیید رمز عبور
                                </button>
                                <a href="/accounts" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-return-right me-2"></i> بازگشت
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center text-muted">
                        <small>
                            در صورت فراموشی رمز عبور، به تنظیمات حساب خود در برنامه تلگرام مراجعه کنید.
                            <?php if (!empty($has_recovery)): ?>
                            <br>یا می‌توانید از <a href="#" class="text-info">کد بازیابی</a> استفاده کنید.
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="/" class="text-muted text-decoration-none">
                        <i class="bi bi-arrow-return-right me-1"></i> بازگشت به صفحه اصلی
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // نمایش/مخفی کردن رمز عبور
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>