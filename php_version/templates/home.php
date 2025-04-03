<?php ob_start(); ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-info-circle"></i> وضعیت سیستم</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>تعداد کل لینک‌ها:</strong>
                    <span class="badge bg-primary"><?= $totalLinks ?></span>
                </div>
                <div class="mb-3">
                    <strong>لینک‌های جدید:</strong>
                    <span class="badge bg-success"><?= $newLinks ?></span>
                </div>
                <div class="mb-3">
                    <strong>آخرین بررسی:</strong>
                    <span><?= $lastCheckTime ?? 'هنوز بررسی نشده' ?></span>
                </div>
                <div class="mb-3">
                    <strong>بررسی بعدی:</strong>
                    <span><?= $nextCheckTime ?? 'نامشخص' ?></span>
                </div>
                <div class="mb-3">
                    <strong>فاصله زمانی بررسی:</strong>
                    <span><?= $checkInterval ?> دقیقه</span>
                </div>
                
                <div class="mt-4">
                    <a href="/check-now" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> بررسی الان
                    </a>
                    <a href="/links" class="btn btn-secondary">
                        <i class="bi bi-link"></i> مشاهده لینک‌ها
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-pie-chart"></i> آمار دسته‌بندی‌ها</h5>
            </div>
            <div class="card-body">
                <?php if (empty($categoryStats)): ?>
                    <p class="text-muted">هنوز لینکی جمع‌آوری نشده است.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>دسته‌بندی</th>
                                    <th>تعداد لینک</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryStats as $category => $count): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category) ?></td>
                                        <td><span class="badge bg-info"><?= $count ?></span></td>
                                        <td>
                                            <a href="/links?category=<?= urlencode($category) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> مشاهده
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
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-link"></i> لینک‌های اخیر</h5>
            </div>
            <div class="card-body">
                <?php if ($newLinks > 0): ?>
                    <div class="alert alert-success">
                        <strong><?= $newLinks ?> لینک جدید یافت شد!</strong>
                        <a href="/links" class="btn btn-sm btn-success ms-2">مشاهده همه</a>
                    </div>
                    
                    <?php 
                    // This would actually be populated from the controller
                    $recentLinks = [];
                    if (isset($links) && is_array($links)) {
                        $recentLinks = array_slice($links, 0, 10);
                    }
                    ?>
                    
                    <?php if (!empty($recentLinks)): ?>
                        <div class="list-group">
                            <?php foreach ($recentLinks as $link): ?>
                                <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($link) ?>
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">هنوز لینک جدیدی یافت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>