<?php

namespace App\Services;

use DateTime;

class AvalaiApi
{
    private string $apiKey;
    private string $baseUrl;
    private string $settingsFile;
    private array $settings;
    private array $chatHistory = [];
    
    public function __construct(string $apiKey = null, string $baseUrl = 'https://api.avalai.ir')
    {
        // Get API key from environment if not provided
        $this->apiKey = $apiKey ?? getenv('AVALAI_API_KEY') ?? '';
        $this->baseUrl = $baseUrl;
        $this->settingsFile = __DIR__ . '/../../avalai_settings.json';
        $this->loadSettings();
    }
    
    private function loadSettings(): void
    {
        $defaultSettings = [
            'enabled' => !empty($this->apiKey),
            'default_prompt' => 'You are a friendly AI assistant. Answer questions and provide information to the best of your knowledge.',
            'answer_all_messages' => false,
            'only_answer_questions' => true,
            'chat_history' => []
        ];
        
        if (file_exists($this->settingsFile)) {
            $jsonData = file_get_contents($this->settingsFile);
            $loadedSettings = json_decode($jsonData, true);
            
            // Merge with default settings to ensure all keys exist
            $this->settings = array_merge($defaultSettings, $loadedSettings);
            $this->chatHistory = $this->settings['chat_history'] ?? [];
        } else {
            $this->settings = $defaultSettings;
            $this->saveSettings($this->settings);
        }
    }
    
    private function saveSettings(array $settings): bool
    {
        $settings['chat_history'] = $this->chatHistory;
        $jsonData = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->settingsFile, $jsonData) !== false;
    }
    
    public function updateSettings(array $settings): bool
    {
        // Merge new settings with existing ones
        $this->settings = array_merge($this->settings, $settings);
        
        // Enforce boolean values for settings that should be boolean
        $this->settings['enabled'] = (bool)($this->settings['enabled'] ?? false);
        $this->settings['answer_all_messages'] = (bool)($this->settings['answer_all_messages'] ?? false);
        $this->settings['only_answer_questions'] = (bool)($this->settings['only_answer_questions'] ?? true);
        
        return $this->saveSettings($this->settings);
    }
    
    public function getSettings(): array
    {
        return $this->settings;
    }
    
    public function isEnabled(): bool
    {
        return !empty($this->apiKey) && ($this->settings['enabled'] ?? false);
    }
    
    public function generateResponse(string $userMessage, ?string $userId = null, ?string $username = null, ?string $conversationId = null, ?array $metadata = null): array
    {
        // If the service is disabled, return early
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Avalai API integration is disabled'
            ];
        }
        
        // If only answering questions and this isn't a question, return early
        if (($this->settings['only_answer_questions'] ?? true) && !$this->isQuestion($userMessage)) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Message is not a question and only_answer_questions is enabled'
            ];
        }
        
        try {
            // Prepare the API request
            $data = [
                'message' => $userMessage,
                'system_message' => $this->settings['default_prompt'] ?? ''
            ];
            
            // Make the API request
            $ch = curl_init($this->baseUrl . '/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'response' => null,
                    'error' => 'API request failed with status code ' . $httpCode
                ];
            }
            
            $responseData = json_decode($response, true);
            $aiResponse = $responseData['choices'][0]['message']['content'] ?? null;
            
            if (!$aiResponse) {
                return [
                    'success' => false,
                    'response' => null,
                    'error' => 'No response content received from API'
                ];
            }
            
            // Log the chat interaction
            $this->logChat($userMessage, $aiResponse, $userId, $username, $metadata);
            
            return [
                'success' => true,
                'response' => $aiResponse,
                'error' => null
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    private function isQuestion(string $text): bool
    {
        // Check if the text contains a question mark
        if (strpos($text, '?') !== false) {
            return true;
        }
        
        // Check for common question words in English
        $englishQuestionWords = ['what', 'who', 'where', 'when', 'why', 'how', 'can', 'could', 'would', 'should', 'is', 'are', 'do', 'does', 'did'];
        $words = preg_split('/\s+/', strtolower(trim($text)));
        if (!empty($words) && in_array($words[0], $englishQuestionWords)) {
            return true;
        }
        
        // Check for common question words in Persian/Farsi
        $persianQuestionWords = ['چه', 'چی', 'کی', 'کجا', 'چگونه', 'چطور', 'چرا', 'آیا', 'کدام', 'چند'];
        foreach ($persianQuestionWords as $word) {
            if (mb_strpos(mb_strtolower($text, 'UTF-8'), $word) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function logChat(string $userMessage, string $aiResponse, ?string $userId = null, ?string $username = null, ?array $metadata = null): void
    {
        $timestamp = time();
        $datetime = (new DateTime())->format('Y-m-d H:i:s');
        
        $entry = [
            'user_id' => $userId,
            'username' => $username,
            'user_message' => $userMessage,
            'ai_response' => $aiResponse,
            'timestamp' => $timestamp,
            'datetime' => $datetime,
            'metadata' => $metadata
        ];
        
        // Add to chat history
        $this->chatHistory[] = $entry;
        
        // Keep only the latest 1000 entries
        if (count($this->chatHistory) > 1000) {
            $this->chatHistory = array_slice($this->chatHistory, -1000);
        }
        
        // Save to settings file
        $this->settings['chat_history'] = $this->chatHistory;
        $this->saveSettings($this->settings);
    }
    
    public function getChatHistory(int $limit = 100, ?string $userId = null): array
    {
        $history = $this->chatHistory;
        
        // Filter by user ID if provided
        if ($userId !== null) {
            $history = array_filter($history, function ($entry) use ($userId) {
                return ($entry['user_id'] ?? null) === $userId;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($history, function ($a, $b) {
            return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
        });
        
        // Limit the number of entries
        return array_slice($history, 0, $limit);
    }
    
    public function clearChatHistory(?string $userId = null): bool
    {
        if ($userId === null) {
            // Clear all chat history
            $this->chatHistory = [];
        } else {
            // Clear only chat history for the specified user
            $this->chatHistory = array_filter($this->chatHistory, function ($entry) use ($userId) {
                return ($entry['user_id'] ?? null) !== $userId;
            });
        }
        
        // Save to settings file
        $this->settings['chat_history'] = $this->chatHistory;
        return $this->saveSettings($this->settings);
    }
    
    public function addSampleMessages(int $count = 5): bool
    {
        $sampleMessages = [
            [
                'user_message' => 'سلام، چطوری؟',
                'ai_response' => 'سلام! من خوبم، ممنون از شما. چطور می‌توانم کمکتان کنم؟'
            ],
            [
                'user_message' => 'میشه یک فیلتر شکن خوب معرفی کنی؟',
                'ai_response' => 'متأسفانه من نمی‌توانم در مورد فیلترشکن‌ها اطلاعات یا توصیه‌ای ارائه دهم. این موضوع می‌تواند برخلاف قوانین باشد. آیا می‌توانم در زمینه دیگری به شما کمک کنم؟'
            ],
            [
                'user_message' => 'معنی زندگی چیست؟',
                'ai_response' => 'سؤال عمیقی پرسیدید! معنی زندگی برای هر فرد می‌تواند متفاوت باشد. برخی آن را در خدمت به دیگران، برخی در رشد شخصی، و بعضی در ایجاد تأثیر مثبت در دنیا می‌دانند. فلاسفه قرن‌ها روی این سؤال تأمل کرده‌اند. شاید زیبایی زندگی در همین جستجوی معنا و کشف مسیر شخصی خودمان باشد. شما چه فکر می‌کنید؟'
            ],
            [
                'user_message' => 'چطور می‌تونم انگلیسی یاد بگیرم؟',
                'ai_response' => 'برای یادگیری زبان انگلیسی، روش‌های مختلفی وجود دارد:\n\n1. استفاده از اپلیکیشن‌های آموزش زبان مثل Duolingo یا Babbel\n2. تماشای فیلم و سریال با زیرنویس انگلیسی\n3. گوش دادن به پادکست‌ها یا موسیقی انگلیسی\n4. مطالعه کتاب‌های ساده به زبان انگلیسی\n5. شرکت در کلاس‌های آنلاین یا حضوری\n6. صحبت با دوستان انگلیسی زبان یا شرکت در گروه‌های مکالمه\n7. نوشتن روزانه به انگلیسی\n\nمهمترین نکته، تداوم و تمرین منظم است. حتی روزی 15 دقیقه تمرین می‌تواند در طولانی مدت نتیجه خوبی داشته باشد.'
            ],
            [
                'user_message' => 'دوست دارم یه شعر بشنوم',
                'ai_response' => 'از سعدی شیرازی:\n\nبنی آدم اعضای یکدیگرند\nکه در آفرینش ز یک گوهرند\nچو عضوی به درد آورد روزگار\nدگر عضوها را نماند قرار\nتو کز محنت دیگران بی‌غمی\nنشاید که نامت نهند آدمی'
            ]
        ];
        
        // Select random sample messages
        $selectedSamples = array_rand($sampleSamples, min($count, count($sampleMessages)));
        
        // If only one sample is selected, make sure it's in an array
        if (!is_array($selectedSamples)) {
            $selectedSamples = [$selectedSamples];
        }
        
        $timestamp = time();
        $datetime = (new DateTime())->format('Y-m-d H:i:s');
        
        // Add sample messages to chat history
        foreach ($selectedSamples as $index) {
            $sample = $sampleMessages[$index];
            $this->chatHistory[] = [
                'user_id' => 'sample_user_' . rand(1, 5),
                'username' => 'کاربر نمونه ' . rand(1, 5),
                'user_message' => $sample['user_message'],
                'ai_response' => $sample['ai_response'],
                'timestamp' => $timestamp - rand(60, 86400), // Random time in the last day
                'datetime' => $datetime,
                'metadata' => [
                    'source' => 'sample'
                ]
            ];
        }
        
        // Save to settings file
        $this->settings['chat_history'] = $this->chatHistory;
        return $this->saveSettings($this->settings);
    }
}