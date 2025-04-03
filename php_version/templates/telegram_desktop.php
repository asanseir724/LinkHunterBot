<?php ob_start(); ?>

<!-- اضافه کردن CSS مخصوص صفحه Telegram Desktop -->
<style>
    .tg-sidebar {
        height: calc(100vh - 150px);
        min-height: 400px;
        overflow-y: auto;
        border-left: 1px solid var(--bs-border-color);
    }
    
    .tg-chat-list {
        overflow-y: auto;
    }
    
    .tg-chat-item {
        padding: 10px;
        border-bottom: 1px solid var(--bs-border-color);
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .tg-chat-item:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    .tg-chat-item.active {
        background-color: rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .tg-main {
        height: calc(100vh - 150px);
        min-height: 400px;
        display: flex;
        flex-direction: column;
    }
    
    .tg-account-selector {
        padding: 10px;
        background-color: var(--bs-dark);
        border-bottom: 1px solid var(--bs-border-color);
    }
    
    .tg-messages {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        display: flex;
        flex-direction: column-reverse;
    }
    
    .tg-message {
        margin-bottom: 15px;
        max-width: 80%;
        padding: 8px 12px;
        border-radius: 8px;
        position: relative;
    }
    
    .tg-message-outgoing {
        background-color: rgba(var(--bs-primary-rgb), 0.2);
        align-self: flex-end;
        border-bottom-right-radius: 0;
    }
    
    .tg-message-incoming {
        background-color: rgba(255, 255, 255, 0.05);
        align-self: flex-start;
        border-bottom-left-radius: 0;
    }
    
    .tg-message-meta {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 4px;
        text-align: left;
    }
    
    .tg-chat-input {
        padding: 10px;
        background-color: var(--bs-dark);
        border-top: 1px solid var(--bs-border-color);
    }
    
    .tg-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background-color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        margin-left: 10px;
    }
    
    .tg-no-chat {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--bs-secondary);
        font-style: italic;
    }
    
    .tg-header {
        padding: 10px 15px;
        border-bottom: 1px solid var(--bs-border-color);
        display: flex;
        align-items: center;
    }
    
    .tg-account-badge {
        display: inline-block;
        background-color: rgba(var(--bs-primary-rgb), 0.2);
        color: var(--bs-primary);
        border-radius: 4px;
        padding: 2px 6px;
        font-size: 0.8rem;
        margin-left: 8px;
    }
</style>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-chat-dots"></i> پیام‌های خصوصی (شبیه‌ساز تلگرام دسکتاپ)</h5>
            </div>
            <div class="card-body p-0">
                <div class="row m-0">
                    <!-- لیست حساب‌ها و چت‌ها -->
                    <div class="col-md-4 p-0">
                        <div class="tg-sidebar">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item bg-dark text-white">
                                    <h6 class="mb-0"><i class="bi bi-person"></i> حساب‌های متصل</h6>
                                </div>
                                
                                <?php if (empty($accounts)): ?>
                                    <div class="list-group-item">
                                        <div class="text-center text-muted py-3">
                                            <i class="bi bi-exclamation-circle"></i> هیچ حساب کاربری متصل نیست.
                                            <div class="mt-2">
                                                <a href="/accounts" class="btn btn-sm btn-outline-primary">مدیریت حساب‌ها</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($accounts as $phone => $account): ?>
                                        <a href="/telegram-desktop?account=<?= urlencode($phone) ?>" class="list-group-item list-group-item-action d-flex align-items-center <?= ($selectedAccount && $selectedAccount['phone'] === $phone) ? 'active' : '' ?>">
                                            <div class="tg-avatar">
                                                <?= mb_substr($account['first_name'] ?: $phone, 0, 1, 'UTF-8') ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold">
                                                    <?php if (!empty($account['first_name']) || !empty($account['last_name'])): ?>
                                                        <?= htmlspecialchars(trim($account['first_name'] . ' ' . $account['last_name'])) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($phone) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php if (!empty($account['username'])): ?>
                                                        @<?= htmlspecialchars($account['username']) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($phone) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($selectedAccount): ?>
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item bg-dark text-white">
                                        <h6 class="mb-0"><i class="bi bi-chat"></i> چت‌ها</h6>
                                    </div>
                                    
                                    <?php if (empty($chats)): ?>
                                        <div class="list-group-item">
                                            <div class="text-center text-muted py-3">
                                                <i class="bi bi-chat-square"></i> هیچ چتی یافت نشد.
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="tg-chat-list">
                                            <?php foreach ($chats as $chat): ?>
                                                <a href="/telegram-desktop?account=<?= urlencode($selectedAccount['phone']) ?>&chat=<?= urlencode($chat['id']) ?>" class="list-group-item list-group-item-action <?= ($selectedChat === $chat['id']) ? 'active' : '' ?>">
                                                    <div class="d-flex">
                                                        <div class="tg-avatar">
                                                            <?= mb_substr($chat['title'], 0, 1, 'UTF-8') ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?= htmlspecialchars($chat['title']) ?></div>
                                                            <?php if (isset($chat['username'])): ?>
                                                                <div class="small text-muted">@<?= htmlspecialchars($chat['username']) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- صفحه اصلی چت -->
                    <div class="col-md-8 p-0">
                        <div class="tg-main">
                            <?php if ($selectedAccount && $selectedChat): ?>
                                <!-- هدر چت -->
                                <div class="tg-header">
                                    <?php 
                                        $chatTitle = 'چت ناشناس';
                                        $chatUsername = null;
                                        
                                        foreach ($chats as $chat) {
                                            if ($chat['id'] === $selectedChat) {
                                                $chatTitle = $chat['title'];
                                                $chatUsername = $chat['username'] ?? null;
                                                break;
                                            }
                                        }
                                    ?>
                                    
                                    <div class="tg-avatar">
                                        <?= mb_substr($chatTitle, 0, 1, 'UTF-8') ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($chatTitle) ?></div>
                                        <?php if ($chatUsername): ?>
                                            <div class="small">@<?= htmlspecialchars($chatUsername) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ms-auto">
                                        <span class="tg-account-badge">
                                            <i class="bi bi-person"></i>
                                            <?= htmlspecialchars($selectedAccount['phone']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- پیام‌ها -->
                                <div class="tg-messages">
                                    <?php if (empty($messages)): ?>
                                        <div class="text-center text-muted my-5">
                                            <i class="bi bi-chat-square"></i> هیچ پیامی یافت نشد.
                                        </div>
                                    <?php else: ?>
                                        <?php 
                                            // مرتب‌سازی پیام‌ها بر اساس زمان (جدیدترین پیام‌ها در پایین)
                                            usort($messages, function($a, $b) {
                                                return ($a['date'] ?? 0) - ($b['date'] ?? 0);
                                            });
                                            
                                            foreach ($messages as $message): 
                                                $isOutgoing = isset($message['out']) && $message['out'];
                                                $messageText = $message['message'] ?? '';
                                                $messageDate = isset($message['date']) ? date('Y-m-d H:i', $message['date']) : '';
                                                
                                                if (empty($messageText)) continue;
                                        ?>
                                            <div class="tg-message <?= $isOutgoing ? 'tg-message-outgoing' : 'tg-message-incoming' ?>">
                                                <div class="tg-message-text"><?= nl2br(htmlspecialchars($messageText)) ?></div>
                                                <div class="tg-message-meta"><?= htmlspecialchars($messageDate) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- فرم ارسال پیام -->
                                <div class="tg-chat-input">
                                    <form action="/telegram-desktop/send-message" method="post">
                                        <input type="hidden" name="account" value="<?= htmlspecialchars($selectedAccount['phone']) ?>">
                                        <input type="hidden" name="chat" value="<?= htmlspecialchars($selectedChat) ?>">
                                        
                                        <div class="input-group">
                                            <input type="text" name="message" class="form-control" placeholder="پیام خود را بنویسید..." required>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="tg-no-chat">
                                    <div class="text-center">
                                        <i class="bi bi-chat-square-dots fs-1 mb-3"></i>
                                        <p>لطفاً یک حساب کاربری و چت را انتخاب کنید.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>