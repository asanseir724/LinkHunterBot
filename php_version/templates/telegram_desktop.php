<?php ob_start(); ?>

<div class="row g-0">
    <!-- Chat Sidebar -->
    <div class="col-md-4 col-lg-3 chat-sidebar">
        <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-chat"></i> گفتگوها</h5>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#avalaiSettingsModal">
                    <i class="bi bi-robot"></i> هوش مصنوعی
                </button>
            </div>
            <input type="text" class="form-control" placeholder="جستجو..." id="searchChats">
        </div>
        
        <div id="chatList">
            <?php if (empty($messages)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-chat-left-text fs-1"></i>
                    <p class="mt-2">هیچ گفتگویی یافت نشد.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $phone => $accountData): ?>
                    <div class="px-3 py-2 border-bottom">
                        <div class="fw-bold text-primary">
                            <i class="bi bi-phone"></i> 
                            <?= htmlspecialchars($accountData['account']['phone']) ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($accountData['account']['username'] ?? 'بدون نام') ?></span>
                        </div>
                    </div>
                    
                    <?php foreach ($accountData['chats'] as $userId => $chatData): ?>
                        <div class="chat-item p-3 border-bottom" data-phone="<?= htmlspecialchars($phone) ?>" data-user-id="<?= htmlspecialchars($userId) ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($chatData['display_name']) ?></div>
                                    <?php 
                                    // Get the most recent message
                                    $lastMessage = !empty($chatData['messages']) ? $chatData['messages'][0] : null;
                                    ?>
                                    <?php if ($lastMessage): ?>
                                        <div class="text-muted small text-truncate" style="max-width: 180px;">
                                            <?= htmlspecialchars(substr($lastMessage['text'], 0, 50)) . (strlen($lastMessage['text']) > 50 ? '...' : '') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($lastMessage): ?>
                                    <div class="text-muted small">
                                        <?= date('H:i', $lastMessage['date']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chat Content -->
    <div class="col-md-8 col-lg-9 chat-content">
        <div id="noChatSelected" class="d-flex flex-column align-items-center justify-content-center h-100">
            <i class="bi bi-chat-text text-muted" style="font-size: 5rem;"></i>
            <h4 class="mt-3 text-muted">گفتگویی انتخاب نشده است</h4>
            <p class="text-muted">از لیست سمت راست یک گفتگو را انتخاب کنید.</p>
        </div>
        
        <div id="chatContainer" class="d-none h-100">
            <!-- Chat Header -->
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="chatName"></h5>
                    <div class="text-muted small" id="chatInfo"></div>
                </div>
                <div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i> عملیات
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" id="clearChatHistory"><i class="bi bi-trash"></i> پاک کردن تاریخچه</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Messages Container -->
            <div class="messages-container" id="messagesContainer">
                <!-- Messages will be populated via JavaScript -->
            </div>
            
            <!-- Message Input -->
            <div class="message-input">
                <form id="messageForm" class="d-flex">
                    <input type="hidden" id="currentPhone" name="phone" value="">
                    <input type="hidden" id="currentUserId" name="user_id" value="">
                    <input type="text" class="form-control me-2" id="messageText" name="text" placeholder="پیام خود را بنویسید...">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Avalai Settings Modal -->
<div class="modal fade" id="avalaiSettingsModal" tabindex="-1" aria-labelledby="avalaiSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="avalaiSettingsModalLabel"><i class="bi bi-robot"></i> تنظیمات هوش مصنوعی</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="avalaiEnabled" <?= $avalaiEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="avalaiEnabled">فعال کردن پاسخ خودکار هوش مصنوعی</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="defaultPrompt" class="form-label">پیام سیستمی پیش‌فرض:</label>
                    <textarea class="form-control" id="defaultPrompt" rows="5"><?= htmlspecialchars($avalaiSettings['default_prompt'] ?? '') ?></textarea>
                    <div class="form-text">این پیام به هوش مصنوعی می‌گوید که چگونه رفتار کند و پاسخ دهد.</div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="answerAllMessages" <?= ($avalaiSettings['answer_all_messages'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="answerAllMessages">پاسخ به همه پیام‌ها</label>
                    </div>
                    <div class="form-text">در صورت فعال بودن، هوش مصنوعی به همه پیام‌ها پاسخ می‌دهد. در غیر این صورت، شما باید برای هر پیام دکمه «پاسخ هوش مصنوعی» را بزنید.</div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="onlyAnswerQuestions" <?= ($avalaiSettings['only_answer_questions'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="onlyAnswerQuestions">فقط به سوالات پاسخ بده</label>
                    </div>
                    <div class="form-text">در صورت فعال بودن، هوش مصنوعی فقط به پیام‌هایی که به نظر می‌رسد سوال هستند پاسخ می‌دهد.</div>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> برای تغییر این تنظیمات، از صفحه «هوش مصنوعی» در منوی اصلی استفاده کنید.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chat related data
    let currentPhone = '';
    let currentUserId = '';
    let allMessages = <?= json_encode($messages) ?>;
    
    // DOM elements
    const chatList = document.getElementById('chatList');
    const noChatSelected = document.getElementById('noChatSelected');
    const chatContainer = document.getElementById('chatContainer');
    const messagesContainer = document.getElementById('messagesContainer');
    const chatName = document.getElementById('chatName');
    const chatInfo = document.getElementById('chatInfo');
    const messageForm = document.getElementById('messageForm');
    const messageText = document.getElementById('messageText');
    const currentPhoneInput = document.getElementById('currentPhone');
    const currentUserIdInput = document.getElementById('currentUserId');
    const searchChats = document.getElementById('searchChats');
    const clearChatHistory = document.getElementById('clearChatHistory');
    
    // Chat item click event
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', function() {
            const phone = this.dataset.phone;
            const userId = this.dataset.userId;
            
            // Remove active class from all chat items
            document.querySelectorAll('.chat-item').forEach(chat => {
                chat.classList.remove('active');
            });
            
            // Add active class to the clicked chat item
            this.classList.add('active');
            
            // Update current chat info
            currentPhone = phone;
            currentUserId = userId;
            currentPhoneInput.value = phone;
            currentUserIdInput.value = userId;
            
            // Show chat container
            noChatSelected.classList.add('d-none');
            chatContainer.classList.remove('d-none');
            
            // Find chat data
            const accountData = allMessages[phone];
            const chatData = accountData.chats[userId];
            
            // Update chat header
            chatName.textContent = chatData.display_name;
            chatInfo.textContent = `${chatData.username ? '@' + chatData.username : ''} - ${userId}`;
            
            // Display messages
            displayMessages(chatData.messages);
        });
    });
    
    // Display messages function
    function displayMessages(messages) {
        messagesContainer.innerHTML = '';
        
        if (!messages || messages.length === 0) {
            messagesContainer.innerHTML = `
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-chat-square-text fs-1"></i>
                    <p class="mt-2">هیچ پیامی یافت نشد.</p>
                </div>
            `;
            return;
        }
        
        // Sort messages by date (oldest first)
        const sortedMessages = [...messages].sort((a, b) => a.date - b.date);
        
        // Display each message
        sortedMessages.forEach(message => {
            const isOutgoing = message.is_outgoing;
            const bubbleClass = isOutgoing ? 'outgoing' : 'incoming';
            
            const messageEl = document.createElement('div');
            messageEl.className = `chat-bubble ${bubbleClass}`;
            
            messageEl.innerHTML = `
                <div class="message-content">${formatMessage(message.text)}</div>
                <div class="message-meta small text-end opacity-75 mt-1">
                    ${formatDate(message.date)}
                </div>
            `;
            
            messagesContainer.appendChild(messageEl);
        });
        
        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Format message text (handle links, line breaks, etc.)
    function formatMessage(text) {
        // Convert URLs to clickable links
        text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
        
        // Convert line breaks to <br>
        text = text.replace(/\n/g, '<br>');
        
        return text;
    }
    
    // Format date for message timestamp
    function formatDate(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
    }
    
    // Message form submit event
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!currentPhone || !currentUserId || !messageText.value.trim()) {
            return;
        }
        
        // Send message via AJAX
        const formData = new FormData(messageForm);
        
        fetch('/accounts/send-message', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add the sent message to the UI
                const newMessage = {
                    message_id: Date.now(),
                    text: messageText.value.trim(),
                    date: Math.floor(Date.now() / 1000),
                    is_outgoing: true,
                    from_id: currentPhone,
                    from_name: 'You'
                };
                
                // Update allMessages data structure
                if (allMessages[currentPhone] && allMessages[currentPhone].chats[currentUserId]) {
                    allMessages[currentPhone].chats[currentUserId].messages.unshift(newMessage);
                    
                    // If there's an AI response, add it too
                    if (data.ai_response) {
                        const aiMessage = {
                            message_id: Date.now() + 1,
                            text: data.ai_response,
                            date: Math.floor(Date.now() / 1000) + 1,
                            is_outgoing: true,
                            from_id: currentPhone,
                            from_name: 'AI'
                        };
                        
                        allMessages[currentPhone].chats[currentUserId].messages.unshift(aiMessage);
                    }
                    
                    // Redisplay messages
                    displayMessages(allMessages[currentPhone].chats[currentUserId].messages);
                }
                
                // Clear message input
                messageText.value = '';
            } else {
                alert('خطا در ارسال پیام.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در ارسال پیام.');
        });
    });
    
    // Search chats functionality
    searchChats.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        document.querySelectorAll('.chat-item').forEach(item => {
            const displayName = item.querySelector('.fw-bold').textContent.toLowerCase();
            const messageText = item.querySelector('.text-muted.small') ? 
                                item.querySelector('.text-muted.small').textContent.toLowerCase() : '';
            
            if (displayName.includes(searchTerm) || messageText.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Clear chat history
    clearChatHistory.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (!currentPhone || !currentUserId) {
            return;
        }
        
        if (confirm('آیا از پاک کردن تاریخچه این گفتگو اطمینان دارید؟')) {
            window.location.href = `/accounts/clear-chat-history?user_id=${currentUserId}`;
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>