<!-- Modal for adding new Telegram account -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addAccountModalLabel">افزودن اکانت تلگرام جدید</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="/accounts/add" method="post">
                    <div class="mb-3">
                        <label for="phone" class="form-label">شماره تلفن (با فرمت بین‌المللی)</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="phone" name="phone" placeholder="+989123456789" required>
                        <div class="form-text text-muted">شماره تلفن را با کد کشور وارد کنید (مثلاً: ۹۸۹۱۲۳۴۵۶۷۸۹+)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">نام (اختیاری)</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="name" name="name" placeholder="اکانت اصلی">
                        <div class="form-text text-muted">نامی برای شناسایی راحت‌تر این اکانت</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>مزیت نسخه جدید:</strong> دیگر نیازی به API ID و API Hash نیست! 
                        <div class="mt-1">این نسخه به صورت خودکار از اطلاعات پیش‌فرض استفاده می‌کند.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">افزودن</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">لغو</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>