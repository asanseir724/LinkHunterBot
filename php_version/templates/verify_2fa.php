<?php ob_start(); ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-shield-lock"></i> احراز هویت دو مرحله‌ای</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    حساب کاربری <strong><?= htmlspecialchars($phone) ?></strong> دارای رمز عبور دو مرحله‌ای است.
                    لطفاً رمز عبور دو مرحله‌ای خود را وارد کنید.
                </div>
                
                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="/accounts/verify-2fa" method="post">
                    <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">رمز عبور دو مرحله‌ای:</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="رمز عبور دو مرحله‌ای خود را وارد کنید" required autofocus>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> تأیید رمز عبور
                        </button>
                        <a href="/accounts" class="btn btn-secondary">
                            <i class="bi bi-arrow-right"></i> انصراف و بازگشت
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>