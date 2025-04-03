<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رابط کاربری تلگرام دسکتاپ</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --telegram-primary: #0088cc;
            --telegram-secondary: #6c7883;
            --dark-bg: #212529;
            --dark-bg-lighter: #2a2f34;
            --card-bg: #282e33;
            --border-color: #343a40;
            --chat-bubble-me: #2b5278;
            --chat-bubble-they: #3a4047;
            --sidebar-width: 280px;
        }
        
        body {
            background-color: var(--dark-bg);
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .telegram-app {
            height: calc(100vh - 56px);
            display: flex;
            overflow: hidden;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--dark-bg-lighter);
            border-left: 1px solid var(--border-color);
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .chat-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .chat-item {
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .chat-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .chat-item.active {
            background-color: rgba(0, 136, 204, 0.1);
            border-right: 3px solid var(--telegram-primary);
        }
        
        .chat-item .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--telegram-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-left: 12px;
        }
        
        .chat-item .chat-name {
            font-weight: 500;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 160px;
        }
        
        .chat-item .chat-preview {
            color: #adb5bd;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 170px;
        }
        
        .chat-item .chat-time {
            font-size: 0.75rem;
            color: #adb5bd;
        }
        
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--card-bg);
            overflow: hidden;
        }
        
        .chat-header {
            padding: 10px 15px;
            background-color: var(--dark-bg-lighter);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .chat-header .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--telegram-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-left: 12px;
        }
        
        .chat-header .chat-info {
            flex: 1;
        }
        
        .chat-header .chat-name {
            font-weight: 500;
            margin-bottom: 0;
        }
        
        .chat-header .chat-status {
            font-size: 0.8rem;
            color: #adb5bd;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background-color: var(--card-bg);
            display: flex;
            flex-direction: column;
        }
        
        .message {
            max-width: 75%;
            margin-bottom: 10px;
            position: relative;
            animation: fadeIn 0.3s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.from-me {
            align-self: flex-end;
        }
        
        .message.from-them {
            align-self: flex-start;
        }
        
        .message .bubble {
            padding: 10px 15px;
            border-radius: 12px;
            position: relative;
        }
        
        .message.from-me .bubble {
            background-color: var(--chat-bubble-me);
            border-bottom-left-radius: 2px;
        }
        
        .message.from-them .bubble {
            background-color: var(--chat-bubble-they);
            border-bottom-right-radius: 2px;
        }
        
        .message .message-text {
            margin-bottom: 5px;
            white-space: pre-wrap;
        }
        
        .message .message-time {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.6);
            text-align: left;
            margin-top: 2px;
        }
        
        .chat-input {
            padding: 15px;
            background-color: var(--dark-bg-lighter);
            border-top: 1px solid var(--border-color);
        }
        
        .chat-input .form-control {
            background-color: #343a40;
            border-color: #495057;
            color: #fff;
            border-radius: 20px;
        }
        
        .chat-input .form-control:focus {
            background-color: #2b3035;
            border-color: var(--telegram-primary);
            box-shadow: 0 0 0 0.25rem rgba(0, 136, 204, 0.25);
            color: #fff;
        }
        
        .ai-response-indicator {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #6c5ce7;
            color: white;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #adb5bd;
            text-align: center;
            padding: 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            color: var(--telegram-primary);
        }
        
        .navbar-dark {
            background-color: var(--dark-bg-lighter);
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-brand img {
            width: 30px;
            height: 30px;
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                right: 0;
                bottom: 0;
                z-index: 1000;
                transform: translateX(100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .chat-toggle-btn {
                display: block !important;
            }
        }
        
        .chat-toggle-btn {
            display: none;
        }
        
        /* اسکرول‌بار سفارشی */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background-color: var(--dark-bg);
        }
        
        ::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background-color: var(--telegram-secondary);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="/assets/img/logo.png" alt="لوگو" class="d-inline-block align-text-top">
                <span>لینکدونی تلگرام</span>
            </a>
            <button class="navbar-toggler chat-toggle-btn me-2" type="button">
                <i class="bi bi-people-fill"></i>
            </button>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="bi bi-house-door me-1"></i> خانه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/links"><i class="bi bi-link-45deg me-1"></i> لینک‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/channels"><i class="bi bi-megaphone me-1"></i> کانال‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/accounts"><i class="bi bi-person me-1"></i> حساب‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/telegram-desktop"><i class="bi bi-chat-dots me-1"></i> پیام‌ها</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/settings"><i class="bi bi-gear me-1"></i> تنظیمات</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/telegram-desktop/add-sample"><i class="bi bi-plus-circle me-1"></i> افزودن پیام نمونه</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/telegram-desktop/clear-history" onclick="return confirm('آیا از پاک کردن تاریخچه چت مطمئن هستید؟')">
                            <i class="bi bi-trash me-1"></i> پاک کردن تاریخچه
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="telegram-app">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="p-2 bg-dark d-flex justify-content-between align-items-center">
                <span><i class="bi bi-search me-1"></i> جستجو</span>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-dark" title="مرتب‌سازی">
                        <i class="bi bi-sort-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-dark" title="تنظیمات">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                </div>
            </div>
            
            <ul class="chat-list" id="chat-list">
                <?php if (empty($accounts)): ?>
                    <li class="p-3 text-center text-muted">
                        <i class="bi bi-chat-left-text"></i>
                        هیچ پیامی یافت نشد.
                    </li>
                <?php else: ?>
                    <?php foreach ($accounts as $chat): ?>
                        <li class="chat-item d-flex align-items-center" data-chat-id="<?php echo $chat['id']; ?>">
                            <div class="avatar">
                                <?php echo strtoupper(substr($chat['name'], 0, 1)); ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="chat-name"><?php echo $chat['name']; ?></div>
                                    <div class="chat-time"><?php echo date('H:i', $chat['last_message_time']); ?></div>
                                </div>
                                <div class="chat-preview"><?php echo htmlspecialchars(substr($chat['last_message'], 0, 50) . (strlen($chat['last_message']) > 50 ? '...' : '')); ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Chat Container -->
        <div class="chat-container">
            <?php if (empty($accounts)): ?>
                <div class="empty-state">
                    <i class="bi bi-chat-left-text"></i>
                    <h3>هیچ پیامی وجود ندارد</h3>
                    <p>برای مشاهده پیام‌ها، ابتدا یک حساب تلگرام را اضافه کنید یا پیام‌های نمونه را اضافه کنید.</p>
                    <div class="mt-3">
                        <a href="/accounts" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i> افزودن حساب
                        </a>
                        <a href="/telegram-desktop/add-sample" class="btn btn-secondary">
                            <i class="bi bi-plus-circle me-1"></i> افزودن پیام نمونه
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Chat Header -->
                <div class="chat-header" id="chat-header" style="display: none;">
                    <div class="avatar" id="chat-avatar"></div>
                    <div class="chat-info">
                        <h5 class="chat-name" id="current-chat-name"></h5>
                        <div class="chat-status" id="chat-status">
                            <span id="chat-online-status">آخرین بازدید لحظاتی پیش</span>
                        </div>
                    </div>
                    <div class="ms-auto">
                        <button class="btn btn-sm btn-outline-secondary" title="پاک کردن تاریخچه این چت" id="clear-chat-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Chat Messages -->
                <div class="chat-messages" id="chat-messages">
                    <div class="empty-state" id="empty-chat-state">
                        <i class="bi bi-chat-right-text"></i>
                        <h4>یک گفتگو را انتخاب کنید</h4>
                        <p>لطفاً از فهرست سمت راست، یک گفتگو را انتخاب کنید.</p>
                    </div>
                </div>
                
                <!-- Chat Input -->
                <div class="chat-input" id="chat-input" style="display: none;">
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" type="button" title="پیوست">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <input type="text" class="form-control" placeholder="پیام..." id="message-input" disabled>
                        <button class="btn btn-primary" type="button" title="ارسال">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                    <small class="text-muted d-block mt-1">
                        <i class="bi bi-info-circle"></i>
                        ارسال پیام از این مرورگر پشتیبانی نمی‌شود. فقط نمایش پیام‌ها فعال است.
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // تابع ذخیره‌سازی چت‌های نمونه (برای نمایش)
            function saveSampleChats() {
                const sampleChats = [
                    {
                        id: 1,
                        name: 'علی محمدی',
                        username: 'alim',
                        avatar: 'ع',
                        last_message: 'سلام، میشه راجع به نسخه VIP توضیح بدین؟',
                        last_message_time: Math.floor(Date.now() / 1000) - 3600,
                        messages: [
                            {
                                id: 1,
                                from_me: false,
                                text: 'سلام، وقت بخیر',
                                time: Math.floor(Date.now() / 1000) - 3700,
                                is_ai: false
                            },
                            {
                                id: 2,
                                from_me: false,
                                text: 'میشه راجع به نسخه VIP توضیح بدین؟',
                                time: Math.floor(Date.now() / 1000) - 3600,
                                is_ai: false
                            },
                            {
                                id: 3,
                                from_me: true,
                                text: 'سلام دوست عزیز\nدر نسخه VIP شما به تمامی امکانات دسترسی خواهید داشت:\n- دریافت لینک گروه‌های خصوصی\n- دسترسی به آرشیو کامل\n- پشتیبانی 24 ساعته\n- و بسیاری امکانات دیگر...',
                                time: Math.floor(Date.now() / 1000) - 3500,
                                is_ai: true
                            }
                        ]
                    },
                    {
                        id: 2,
                        name: 'زهرا احمدی',
                        username: 'zahra_a',
                        avatar: 'ز',
                        last_message: 'ممنون از راهنمایی‌تون',
                        last_message_time: Math.floor(Date.now() / 1000) - 7200,
                        messages: [
                            {
                                id: 1,
                                from_me: false,
                                text: 'سلام، میخواستم بدونم چطور میتونم گروه خودم رو به ربات اضافه کنم؟',
                                time: Math.floor(Date.now() / 1000) - 7500,
                                is_ai: false
                            },
                            {
                                id: 2,
                                from_me: true,
                                text: 'سلام، برای اضافه کردن گروهتون به ربات، می‌تونید از طریق منوی "افزودن کانال" اقدام کنید. کافیه لینک کانال یا گروهتون رو وارد کنید یا ربات ما رو به گروه اضافه کنید.',
                                time: Math.floor(Date.now() / 1000) - 7400,
                                is_ai: true
                            },
                            {
                                id: 3,
                                from_me: false,
                                text: 'ممنون از راهنمایی‌تون',
                                time: Math.floor(Date.now() / 1000) - 7200,
                                is_ai: false
                            }
                        ]
                    },
                    {
                        id: 3,
                        name: 'محمد حسینی',
                        username: 'mammad',
                        avatar: 'م',
                        last_message: 'سلام، میخوام یه vpn خوب بخرم. قیمتش چقدره؟',
                        last_message_time: Math.floor(Date.now() / 1000) - 86400,
                        messages: [
                            {
                                id: 1,
                                from_me: false,
                                text: 'سلام، میخوام یه vpn خوب بخرم. قیمتش چقدره؟',
                                time: Math.floor(Date.now() / 1000) - 86400,
                                is_ai: false
                            },
                            {
                                id: 2,
                                from_me: true,
                                text: 'سلام، ما سرویس vpn نمی‌فروشیم و این کار غیرقانونی است. لطفاً جهت این موضوعات از منابع قانونی استفاده کنید. ربات ما فقط مربوط به اشتراک‌گذاری لینک‌های گروه‌های تلگرام است.',
                                time: Math.floor(Date.now() / 1000) - 86300,
                                is_ai: true
                            }
                        ]
                    }
                ];
                
                localStorage.setItem('sampleChats', JSON.stringify(sampleChats));
                return sampleChats;
            }
            
            // بارگذاری چت‌ها
            let chats = JSON.parse(localStorage.getItem('sampleChats')) || [];
            if (!chats.length) {
                chats = saveSampleChats();
            }
            
            const chatList = document.getElementById('chat-list');
            const chatMessages = document.getElementById('chat-messages');
            const chatHeader = document.getElementById('chat-header');
            const chatInput = document.getElementById('chat-input');
            const emptyState = document.getElementById('empty-chat-state');
            const currentChatName = document.getElementById('current-chat-name');
            const chatAvatar = document.getElementById('chat-avatar');
            const chatStatus = document.getElementById('chat-online-status');
            const clearChatBtn = document.getElementById('clear-chat-btn');
            
            // رویداد کلیک روی چت‌ها
            chatList.addEventListener('click', function(e) {
                const chatItem = e.target.closest('.chat-item');
                if (!chatItem) return;
                
                // فعال کردن آیتم انتخاب شده
                document.querySelectorAll('.chat-item').forEach(item => {
                    item.classList.remove('active');
                });
                chatItem.classList.add('active');
                
                // نمایش پیام‌ها
                const chatId = parseInt(chatItem.dataset.chatId);
                const selectedChat = chats.find(chat => chat.id === chatId);
                
                if (selectedChat) {
                    showChat(selectedChat);
                    
                    // در موبایل، سایدبار را مخفی کنیم
                    if (window.innerWidth < 768) {
                        document.querySelector('.sidebar').classList.remove('show');
                    }
                }
            });
            
            // نمایش پیام‌های یک چت
            function showChat(chat) {
                chatHeader.style.display = 'flex';
                chatInput.style.display = 'block';
                emptyState.style.display = 'none';
                
                currentChatName.textContent = chat.name;
                chatAvatar.textContent = chat.avatar;
                
                // زمان آخرین بازدید
                const lastMessageTime = new Date(chat.last_message_time * 1000);
                const now = new Date();
                const diffHours = Math.floor((now - lastMessageTime) / (1000 * 60 * 60));
                
                if (diffHours < 1) {
                    chatStatus.textContent = 'آخرین بازدید لحظاتی پیش';
                } else if (diffHours < 24) {
                    chatStatus.textContent = `آخرین بازدید ${diffHours} ساعت پیش`;
                } else {
                    const days = Math.floor(diffHours / 24);
                    chatStatus.textContent = `آخرین بازدید ${days} روز پیش`;
                }
                
                // نمایش پیام‌ها
                chatMessages.innerHTML = '';
                chat.messages.forEach(message => {
                    const messageElement = document.createElement('div');
                    messageElement.classList.add('message', message.from_me ? 'from-me' : 'from-them');
                    
                    const bubble = document.createElement('div');
                    bubble.classList.add('bubble');
                    
                    const messageText = document.createElement('div');
                    messageText.classList.add('message-text');
                    messageText.textContent = message.text;
                    
                    const messageTime = document.createElement('div');
                    messageTime.classList.add('message-time');
                    messageTime.textContent = formatTime(message.time);
                    
                    bubble.appendChild(messageText);
                    bubble.appendChild(messageTime);
                    messageElement.appendChild(bubble);
                    
                    // اگر پیام توسط هوش مصنوعی تولید شده باشد
                    if (message.is_ai && message.from_me) {
                        const aiIndicator = document.createElement('div');
                        aiIndicator.classList.add('ai-response-indicator');
                        aiIndicator.innerHTML = '<i class="bi bi-robot"></i>';
                        messageElement.appendChild(aiIndicator);
                    }
                    
                    chatMessages.appendChild(messageElement);
                });
                
                // اسکرول به آخرین پیام
                chatMessages.scrollTop = chatMessages.scrollHeight;
                
                // تنظیم رویداد دکمه حذف
                clearChatBtn.onclick = function() {
                    if (confirm('آیا از پاک کردن تاریخچه این چت مطمئن هستید؟')) {
                        // در اینجا حذف پیام‌ها را پیاده‌سازی کنید
                        alert('تاریخچه چت پاک شد.');
                        window.location.href = `/telegram-desktop/clear-history/${chat.id}`;
                    }
                };
            }
            
            // فرمت کردن زمان پیام
            function formatTime(timestamp) {
                const date = new Date(timestamp * 1000);
                return date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
            }
            
            // رویداد دکمه تاگل سایدبار برای موبایل
            document.querySelector('.chat-toggle-btn').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('show');
            });
            
            // اگر چت‌ها وجود داشته باشند، اولین چت را نمایش دهیم
            if (chats.length > 0 && document.querySelector('.chat-item')) {
                document.querySelector('.chat-item').click();
            }
        });
    </script>
</body>
</html>