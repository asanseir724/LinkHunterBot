<!DOCTYPE html>
<html lang="fa" dir="rtl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استخراج کننده لینک تلگرام</title>
    <link rel="stylesheet" href="https://cdn.replit.com/agent/bootstrap-agent-dark-theme.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
        }
        .rtl {
            direction: rtl;
            text-align: right;
        }
        .ltr {
            direction: ltr;
            text-align: left;
        }
        
        /* Telegram Desktop style */
        .chat-sidebar {
            height: calc(100vh - 56px);
            overflow-y: auto;
            border-left: 1px solid var(--bs-border-color);
        }
        .chat-content {
            height: calc(100vh - 56px);
            display: flex;
            flex-direction: column;
        }
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        .message-input {
            border-top: 1px solid var(--bs-border-color);
            padding: 1rem;
        }
        .chat-bubble {
            max-width: 80%;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
        }
        .outgoing {
            background-color: var(--bs-primary);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0;
        }
        .incoming {
            background-color: var(--bs-secondary);
            margin-right: auto;
            border-bottom-left-radius: 0;
        }
        .chat-item {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .chat-item:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.1);
        }
        .chat-item.active {
            background-color: rgba(var(--bs-primary-rgb), 0.2);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/"><i class="bi bi-telegram"></i> استخراج کننده لینک تلگرام</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="bi bi-house"></i> خانه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/channels"><i class="bi bi-broadcast"></i> کانال‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/links"><i class="bi bi-link"></i> لینک‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/accounts"><i class="bi bi-person"></i> حساب‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/telegram-desktop"><i class="bi bi-chat"></i> پیام‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/settings"><i class="bi bi-gear"></i> تنظیمات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/avalai-settings"><i class="bi bi-robot"></i> هوش مصنوعی</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="/check-now" class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise"></i> بررسی الان</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (isset($flashMessage)): ?>
            <div class="alert alert-<?= $flashMessage['type'] ?? 'info' ?> alert-dismissible fade show">
                <?= $flashMessage['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?= $content ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>