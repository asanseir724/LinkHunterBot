<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأیید رمز عبور دو مرحله‌ای تلگرام</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .telegram-color {
            background-color: #0088cc;
            color: white;
        }
        .form-control:focus {
            border-color: #0088cc;
            box-shadow: 0 0 0 0.25rem rgba(0, 136, 204, 0.25);
        }
        .hint {
            background-color: #e9f5fe;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header telegram-color text-center">
                        <h3><i class="bi bi-lock-fill me-2"></i> احراز هویت دو مرحله‌ای تلگرام</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="lead text-center mb-4">
                            حساب شما با شماره <?php echo $phone; ?> دارای احراز هویت دو مرحله‌ای است.
                            <br>لطفاً رمز عبور دو مرحله‌ای خود را وارد کنید.
                        </p>
                        
                        <?php if (!empty($hint)): ?>
                        <div class="hint mb-4">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>راهنمای رمز عبور: </strong><?php echo $hint; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form action="/accounts/verify-2fa" method="post">
                            <input type="hidden" name="phone" value="<?php echo $phone; ?>">
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">رمز عبور دو مرحله‌ای</label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="رمز عبور دو مرحله‌ای را وارد کنید" required autofocus>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg telegram-color">
                                    <i class="bi bi-check-circle me-2"></i> تأیید رمز عبور
                                </button>
                                <a href="/accounts" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-return-right me-2"></i> بازگشت
                                </a>
                            </div>
                        </form>
                        
                        <?php if (!empty($has_recovery)): ?>
                        <div class="mt-4 text-center">
                            <a href="/accounts/recovery/<?php echo urlencode($phone); ?>" class="link-primary">
                                <i class="bi bi-question-circle me-1"></i> آیا رمز عبور خود را فراموش کرده‌اید؟
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center text-muted">
                        <small>
                            این رمز عبور همان رمز عبوری است که هنگام فعال‌سازی احراز هویت دو مرحله‌ای در تلگرام تنظیم کرده‌اید.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // نمایش/مخفی کردن رمز عبور
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            // تغییر نوع ورودی بین password و text
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // تغییر آیکون
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>