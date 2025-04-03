<?php ob_start(); ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-shield-lock"></i> تأیید کد احراز هویت</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    کد تأیید به شماره <strong><?= htmlspecialchars($phone) ?></strong> ارسال شده است.
                    لطفاً کد را در فیلد زیر وارد کنید.
                </div>
                
                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="/accounts/verify-code" method="post">
                    <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">کد تأیید:</label>
                        <input type="text" class="form-control text-center" id="code" name="code" placeholder="کد تأیید را وارد کنید" required autofocus autocomplete="one-time-code">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> تأیید کد
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