<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأیید کد تلگرام</title>
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
        .countdown {
            font-size: 1.2rem;
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header telegram-color text-center">
                        <h3><i class="bi bi-telegram me-2"></i> تأیید کد تلگرام</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="lead text-center mb-4">
                            کد تأیید به شماره <?php echo $phone; ?> ارسال شده است.
                            <br>لطفاً کد را وارد کنید.
                        </p>
                        
                        <?php if (!empty($_SESSION['code_timeout'])): ?>
                            <div class="text-center mb-3">
                                <span class="countdown" id="countdown" data-timeout="<?php echo $_SESSION['code_timeout']; ?>"></span>
                            </div>
                        <?php endif; ?>
                        
                        <form action="/accounts/verify-code" method="post">
                            <input type="hidden" name="phone" value="<?php echo $phone; ?>">
                            
                            <div class="mb-4">
                                <label for="code" class="form-label">کد تأیید</label>
                                <input type="text" class="form-control form-control-lg text-center" id="code" name="code" placeholder="کد ۵ رقمی را وارد کنید" required autofocus maxlength="5" inputmode="numeric" pattern="[0-9]*">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg telegram-color">
                                    <i class="bi bi-check-circle me-2"></i> تأیید کد
                                </button>
                                <a href="/accounts" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-return-right me-2"></i> بازگشت
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center text-muted">
                        <small>
                            کد تأیید را از چت تلگرام خود با شماره رسمی تلگرام یا در اپلیکیشن تلگرام خود پیدا کنید.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // شمارنده معکوس برای مدت اعتبار کد
        const countdownEl = document.getElementById('countdown');
        if (countdownEl) {
            const timeout = parseInt(countdownEl.dataset.timeout) || 120;
            let remaining = timeout;
            
            function updateCountdown() {
                if (remaining <= 0) {
                    countdownEl.textContent = 'کد منقضی شده است';
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                countdownEl.textContent = `زمان باقیمانده: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                remaining--;
                setTimeout(updateCountdown, 1000);
            }
            
            updateCountdown();
        }
    </script>
</body>
</html>