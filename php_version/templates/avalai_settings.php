<?php ob_start(); ?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-robot"></i> تنظیمات هوش مصنوعی Avalai</h5>
            </div>
            <div class="card-body">
                <?php if (!$avalaiEnabled): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> هوش مصنوعی Avalai غیرفعال است. برای فعال‌سازی، API Key را در متغیرهای محیطی تنظیم کنید.
                    </div>
                <?php endif; ?>
                
                <form action="/avalai-settings/update" method="post">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?= isset($avalaiSettings['enabled']) && $avalaiSettings['enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enabled">فعال کردن پاسخ خودکار</label>
                        </div>
                        <div class="form-text">در صورت فعال بودن، هوش مصنوعی به صورت خودکار به پیام‌های دریافتی پاسخ می‌دهد.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_prompt" class="form-label">پیام سیستمی پیش‌فرض:</label>
                        <textarea class="form-control" id="default_prompt" name="default_prompt" rows="6"><?= htmlspecialchars($avalaiSettings['default_prompt'] ?? '') ?></textarea>
                        <div class="form-text">این پیام به هوش مصنوعی می‌گوید که چگونه رفتار کند و پاسخ دهد.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="answer_all_messages" name="answer_all_messages" value="1" <?= isset($avalaiSettings['answer_all_messages']) && $avalaiSettings['answer_all_messages'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="answer_all_messages">پاسخ به همه پیام‌ها</label>
                        </div>
                        <div class="form-text">در صورت فعال بودن، هوش مصنوعی به همه پیام‌ها پاسخ می‌دهد. در غیر این صورت، شما باید برای هر پیام دکمه «پاسخ هوش مصنوعی» را بزنید.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="only_answer_questions" name="only_answer_questions" value="1" <?= isset($avalaiSettings['only_answer_questions']) && $avalaiSettings['only_answer_questions'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="only_answer_questions">فقط به سوالات پاسخ بده</label>
                        </div>
                        <div class="form-text">در صورت فعال بودن، هوش مصنوعی فقط به پیام‌هایی که به نظر می‌رسد سوال هستند پاسخ می‌دهد.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> ذخیره تنظیمات
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-chat-text"></i> تاریخچه چت</h5>
                <div>
                    <form action="/avalai-settings/update" method="post" class="d-inline-block">
                        <input type="hidden" name="add_sample_messages" value="1">
                        <input type="hidden" name="sample_count" value="5">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-plus-circle"></i> افزودن پیام‌های نمونه
                        </button>
                    </form>
                    <a href="/accounts/clear-chat-history" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i> پاک کردن تاریخچه
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($chatHistory)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> هنوز هیچ چتی انجام نشده است.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>کاربر</th>
                                    <th>پیام کاربر</th>
                                    <th>پاسخ هوش مصنوعی</th>
                                    <th>زمان</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chatHistory as $entry): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($entry['username'])): ?>
                                                <strong><?= htmlspecialchars($entry['username']) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted"><?= htmlspecialchars($entry['user_id'] ?? 'ناشناس') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="max-height: 100px; overflow-y: auto;">
                                                <?= nl2br(htmlspecialchars($entry['user_message'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="max-height: 100px; overflow-y: auto;">
                                                <?= nl2br(htmlspecialchars($entry['ai_response'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($entry['datetime'] ?? date('Y-m-d H:i:s', $entry['timestamp'] ?? time())) ?>
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

<?php
$content = ob_get_clean();
include 'layout.php';
?>