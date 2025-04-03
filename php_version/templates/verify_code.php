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
        .code-input {
            letter-spacing: 0.5em;
            text-align: center;
            font-size: 1.5em;
        }
        .code-hint {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header telegram-color text-center">
                        <h3><i class="bi bi-shield-lock me-2"></i> تأیید کد تلگرام</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="lead text-center mb-4">
                            کد تأیید به تلگرام شما با شماره <?php echo $phone; ?> ارسال شده است.
                            <br>لطفاً کد را در کادر زیر وارد کنید.
                        </p>
                        
                        <form action="/accounts/verify-code" method="post">
                            <input type="hidden" name="phone" value="<?php echo $phone; ?>">
                            <input type="hidden" name="phone_code_hash" value="<?php echo $phone_code_hash; ?>">
                            
                            <div class="mb-4">
                                <label for="code" class="form-label">کد تأیید</label>
                                <input type="text" class="form-control form-control-lg code-input" id="code" name="code" placeholder="کد تأیید" required autofocus maxlength="5" pattern="[0-9]*">
                                <div class="code-hint mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    کد تأیید در پیام‌های تلگرام شما ارسال شده است.
                                </div>
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
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <form action="/accounts/resend-code" method="post" class="me-2">
                                <input type="hidden" name="phone" value="<?php echo $phone; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-repeat me-1"></i> ارسال مجدد کد
                                </button>
                            </form>
                            <small class="text-muted">در صورت عدم دریافت کد، می‌توانید درخواست ارسال مجدد کنید.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // اعمال فقط مقادیر عددی در فیلد کد
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>