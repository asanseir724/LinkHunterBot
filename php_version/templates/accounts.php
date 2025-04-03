<?php ob_start(); ?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-person"></i> مدیریت حساب‌های کاربری تلگرام</h5>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                    <i class="bi bi-plus-circle"></i> افزودن حساب جدید
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($accounts)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> هنوز هیچ حساب کاربری اضافه نشده است.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>شماره تلفن</th>
                                    <th>نام کاربری</th>
                                    <th>نام</th>
                                    <th>وضعیت</th>
                                    <th>آخرین بررسی</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($account['phone']) ?></td>
                                        <td>
                                            <?php if (!empty($account['username'])): ?>
                                                @<?= htmlspecialchars($account['username']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $name = trim($account['first_name'] . ' ' . $account['last_name']);
                                                echo !empty($name) ? htmlspecialchars($name) : '<span class="text-muted">-</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($account['connected']): ?>
                                                <span class="badge bg-success">متصل</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">قطع</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                echo isset($account['last_check_time']) && !empty($account['last_check_time']) 
                                                    ? date('Y-m-d H:i:s', $account['last_check_time']) 
                                                    : '<span class="text-muted">هرگز</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($account['connected']): ?>
                                                    <a href="/accounts/disconnect/<?= urlencode($account['phone']) ?>" class="btn btn-sm btn-warning" onclick="return confirm('آیا از قطع اتصال این حساب اطمینان دارید؟')">
                                                        <i class="bi bi-power"></i> قطع اتصال
                                                    </a>
                                                <?php else: ?>
                                                    <a href="/accounts/connect/<?= urlencode($account['phone']) ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-link"></i> اتصال
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="/accounts/remove/<?= urlencode($account['phone']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این حساب اطمینان دارید؟')">
                                                    <i class="bi bi-trash"></i> حذف
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <?php if (!empty($accounts)): ?>
                        <a href="/accounts/check-links" class="btn btn-primary">
                            <i class="bi bi-search"></i> بررسی لینک‌ها در همه حساب‌ها
                        </a>
                    <?php endif; ?>
                    
                    <a href="/telegram-desktop" class="btn btn-info">
                        <i class="bi bi-chat"></i> مشاهده پیام‌های خصوصی
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAccountModalLabel"><i class="bi bi-person-plus"></i> افزودن حساب کاربری جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/accounts/add" method="post">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> شماره تلفن را با فرمت بین‌المللی وارد کنید (مثال: ۹۸۹۱۲۳۴۵۶۷۸۹+)
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">شماره تلفن:</label>
                        <div class="input-group">
                            <span class="input-group-text">+</span>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="989123456789" required>
                        </div>
                        <div class="form-text">شماره تلفن باید با کد کشور و بدون فاصله وارد شود.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> افزودن حساب
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>