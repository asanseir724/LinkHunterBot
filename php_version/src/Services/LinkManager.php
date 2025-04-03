<?php

namespace App\Services;

class LinkManager
{
    private string $dataFile;
    private array $data;
    
    public function __construct(string $dataFile = 'links_data.json')
    {
        $this->dataFile = $dataFile;
        $this->loadData();
    }
    
    private function loadData(): void
    {
        $defaultData = [
            'telegram_token' => '',
            'telegram_tokens' => [],
            'channels' => [],
            'websites' => [],
            'links' => [],
            'new_links' => [],
            'categories' => [
                'عمومی' => ['عمومی', 'گروه', 'چت', 'گپ'],
                'فیلم و سریال' => ['فیلم', 'سریال', 'انیمیشن', 'کارتون', 'movie', 'film', 'cinema', 'پویا'],
                'موسیقی' => ['موسیقی', 'آهنگ', 'موزیک', 'music', 'song', 'mp3'],
                'ورزشی' => ['ورزش', 'فوتبال', 'والیبال', 'بسکتبال', 'استقلال', 'پرسپولیس', 'sport'],
                'خبری' => ['خبر', 'اخبار', 'news', 'جدید', 'تازه'],
                'علمی و آموزشی' => ['آموزش', 'علم', 'دانش', 'یادگیری', 'learn', 'education', 'کنکور', 'درس'],
                'سرگرمی' => ['سرگرمی', 'جوک', 'طنز', 'خنده', 'فان', 'فان']
            ],
            'category_keywords' => [
                'عمومی' => ['عمومی', 'گروه', 'چت', 'گپ'],
                'فیلم و سریال' => ['فیلم', 'سریال', 'انیمیشن', 'کارتون', 'movie', 'film', 'cinema', 'پویا'],
                'موسیقی' => ['موسیقی', 'آهنگ', 'موزیک', 'music', 'song', 'mp3'],
                'ورزشی' => ['ورزش', 'فوتبال', 'والیبال', 'بسکتبال', 'استقلال', 'پرسپولیس', 'sport'],
                'خبری' => ['خبر', 'اخبار', 'news', 'جدید', 'تازه'],
                'علمی و آموزشی' => ['آموزش', 'علم', 'دانش', 'یادگیری', 'learn', 'education', 'کنکور', 'درس'],
                'سرگرمی' => ['سرگرمی', 'جوک', 'طنز', 'خنده', 'فان', 'فان']
            ],
            'channel_categories' => [],
            'website_categories' => [],
            'check_interval' => 5,
            'scroll_count' => 10,
            'last_check_time' => null
        ];
        
        if (file_exists($this->dataFile)) {
            $jsonData = file_get_contents($this->dataFile);
            $loadedData = json_decode($jsonData, true);
            
            // Merge with default data to ensure all keys exist
            $this->data = array_merge($defaultData, $loadedData);
        } else {
            $this->data = $defaultData;
            $this->saveData();
        }
    }
    
    private function saveData(): bool
    {
        $jsonData = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->dataFile, $jsonData) !== false;
    }
    
    public function setTelegramToken(string $token): void
    {
        $this->data['telegram_token'] = $token;
        $this->saveData();
    }
    
    public function addTelegramToken(string $token): void
    {
        if (!in_array($token, $this->data['telegram_tokens'])) {
            $this->data['telegram_tokens'][] = $token;
            $this->saveData();
        }
    }
    
    public function removeTelegramToken(string $token): void
    {
        $index = array_search($token, $this->data['telegram_tokens']);
        if ($index !== false) {
            unset($this->data['telegram_tokens'][$index]);
            $this->data['telegram_tokens'] = array_values($this->data['telegram_tokens']);
            $this->saveData();
        }
    }
    
    public function getTelegramToken(): string
    {
        if (!empty($this->data['telegram_tokens'])) {
            // Use token rotation
            $token = array_shift($this->data['telegram_tokens']);
            $this->data['telegram_tokens'][] = $token;
            $this->saveData();
            return $token;
        }
        
        return $this->data['telegram_token'];
    }
    
    public function getAllTelegramTokens(): array
    {
        return array_merge([$this->data['telegram_token']], $this->data['telegram_tokens']);
    }
    
    public function addChannel(string $channel, string $category = 'عمومی'): bool
    {
        // Normalize the channel name
        $normalizedChannel = $this->normalizeChannelName($channel);
        
        if (empty($normalizedChannel)) {
            return false;
        }
        
        if (!in_array($normalizedChannel, $this->data['channels'])) {
            $this->data['channels'][] = $normalizedChannel;
            $this->data['channel_categories'][$normalizedChannel] = $category;
            $this->saveData();
            return true;
        }
        
        return false;
    }
    
    private function normalizeChannelName(string $channel): string
    {
        // Remove any whitespace
        $channel = trim($channel);
        
        // Remove the @ if it exists
        if (strpos($channel, '@') === 0) {
            $channel = substr($channel, 1);
        }
        
        // If it's a full URL, extract the channel name
        if (strpos($channel, 'https://t.me/') === 0) {
            $channel = substr($channel, strlen('https://t.me/'));
        }
        
        return $channel;
    }
    
    public function removeChannel(string $channel): bool
    {
        $normalizedChannel = $this->normalizeChannelName($channel);
        $index = array_search($normalizedChannel, $this->data['channels']);
        
        if ($index !== false) {
            unset($this->data['channels'][$index]);
            $this->data['channels'] = array_values($this->data['channels']);
            
            if (isset($this->data['channel_categories'][$normalizedChannel])) {
                unset($this->data['channel_categories'][$normalizedChannel]);
            }
            
            $this->saveData();
            return true;
        }
        
        return false;
    }
    
    public function getChannels(): array
    {
        return $this->data['channels'];
    }
    
    public function addWebsite(string $url, string $category = 'عمومی'): bool
    {
        // Normalize URL
        $normalizedUrl = $this->normalizeUrl($url);
        
        if (empty($normalizedUrl)) {
            return false;
        }
        
        if (!in_array($normalizedUrl, $this->data['websites'])) {
            $this->data['websites'][] = $normalizedUrl;
            $this->data['website_categories'][$normalizedUrl] = $category;
            $this->saveData();
            return true;
        }
        
        return false;
    }
    
    private function normalizeUrl(string $url): string
    {
        // Add https:// if missing
        if (strpos($url, 'http') !== 0) {
            $url = 'https://' . $url;
        }
        
        return $url;
    }
    
    public function removeWebsite(string $url): bool
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $index = array_search($normalizedUrl, $this->data['websites']);
        
        if ($index !== false) {
            unset($this->data['websites'][$index]);
            $this->data['websites'] = array_values($this->data['websites']);
            
            if (isset($this->data['website_categories'][$normalizedUrl])) {
                unset($this->data['website_categories'][$normalizedUrl]);
            }
            
            $this->saveData();
            return true;
        }
        
        return false;
    }
    
    public function getWebsites(): array
    {
        return $this->data['websites'];
    }
    
    public function addLink(string $link, ?string $source = null, ?string $messageText = null): bool
    {
        // Check if the link already exists
        if (in_array($link, $this->data['links'])) {
            return false;
        }
        
        // Detect category from keywords if message text is provided
        $category = 'عمومی';
        if ($messageText) {
            $detectedCategory = $this->detectCategoryFromKeywords($messageText);
            if ($detectedCategory) {
                $category = $detectedCategory;
            } else if ($source && isset($this->data['channel_categories'][$source])) {
                // Use the channel's category if no specific category was detected
                $category = $this->data['channel_categories'][$source];
            }
        } else if ($source && isset($this->data['channel_categories'][$source])) {
            $category = $this->data['channel_categories'][$source];
        }
        
        // Add to links
        $this->data['links'][] = $link;
        $this->data['new_links'][] = $link;
        
        // Store with metadata
        $linkData = [
            'url' => $link,
            'source' => $source,
            'category' => $category,
            'timestamp' => time()
        ];
        
        $this->data['link_metadata'][$link] = $linkData;
        $this->saveData();
        
        return true;
    }
    
    private function detectCategoryFromKeywords(string $text): ?string
    {
        $text = mb_strtolower($text);
        $maxMatches = 0;
        $bestCategory = null;
        
        foreach ($this->data['category_keywords'] as $category => $keywords) {
            $matches = 0;
            foreach ($keywords as $keyword) {
                if (mb_strpos($text, mb_strtolower($keyword)) !== false) {
                    $matches++;
                }
            }
            
            if ($matches > $maxMatches) {
                $maxMatches = $matches;
                $bestCategory = $category;
            }
        }
        
        return ($maxMatches > 0) ? $bestCategory : null;
    }
    
    public function getAllLinks(): array
    {
        return $this->data['links'];
    }
    
    public function getNewLinks(): array
    {
        return $this->data['new_links'];
    }
    
    public function getLinksByCategory(?string $category = null): array
    {
        if ($category === null) {
            $result = [];
            foreach ($this->data['link_metadata'] ?? [] as $link => $metadata) {
                $cat = $metadata['category'] ?? 'عمومی';
                if (!isset($result[$cat])) {
                    $result[$cat] = [];
                }
                $result[$cat][] = $link;
            }
            return $result;
        } else {
            $links = [];
            foreach ($this->data['link_metadata'] ?? [] as $link => $metadata) {
                if (($metadata['category'] ?? 'عمومی') === $category) {
                    $links[] = $link;
                }
            }
            return $links;
        }
    }
    
    public function getCategories(): array
    {
        return array_keys($this->data['categories']);
    }
    
    public function getCategoryKeywords(?string $category = null): array
    {
        if ($category === null) {
            return $this->data['category_keywords'];
        }
        
        return $this->data['category_keywords'][$category] ?? [];
    }
    
    public function updateCategoryKeywords(string $category, array $keywords): bool
    {
        if (isset($this->data['category_keywords'][$category])) {
            $this->data['category_keywords'][$category] = $keywords;
            $this->saveData();
            return true;
        }
        
        return false;
    }
    
    public function clearLinks(): void
    {
        $this->data['links'] = [];
        $this->data['new_links'] = [];
        $this->data['link_metadata'] = [];
        $this->saveData();
    }
    
    public function clearNewLinks(): void
    {
        $this->data['new_links'] = [];
        $this->saveData();
    }
    
    public function setCheckInterval(int $minutes): void
    {
        $this->data['check_interval'] = max(1, $minutes);
        $this->saveData();
    }
    
    public function getCheckInterval(): int
    {
        return $this->data['check_interval'];
    }
    
    public function updateLastCheckTime(): void
    {
        $this->data['last_check_time'] = time();
        $this->saveData();
    }
    
    public function getLastCheckTime(): ?int
    {
        return $this->data['last_check_time'];
    }
    
    public function exportLinksToExcel(string $filename, ?string $category = null): ?string
    {
        // In a real implementation, this would use a PHP Excel library
        // For this example, we'll create a CSV file instead
        
        $links = ($category === null) ? $this->getAllLinks() : $this->getLinksByCategory($category);
        
        if (empty($links)) {
            return null;
        }
        
        $csvFile = fopen($filename, 'w');
        
        // Write header
        fputcsv($csvFile, ['URL', 'Category', 'Source', 'Timestamp']);
        
        // Write data
        foreach ($links as $link) {
            $metadata = $this->data['link_metadata'][$link] ?? [
                'url' => $link,
                'category' => 'عمومی',
                'source' => 'Unknown',
                'timestamp' => time()
            ];
            
            fputcsv($csvFile, [
                $metadata['url'],
                $metadata['category'],
                $metadata['source'],
                date('Y-m-d H:i:s', $metadata['timestamp'])
            ]);
        }
        
        fclose($csvFile);
        
        return $filename;
    }
}