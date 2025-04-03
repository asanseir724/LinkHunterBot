<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Logger;
use danog\MadelineProto\EventHandler;

class TelegramBot
{
    private LinkManager $linkManager;
    private ?API $api = null;
    private array $messageHandlers = [];
    
    public function __construct(LinkManager $linkManager)
    {
        $this->linkManager = $linkManager;
        
        // Set up session file path
        $sessionFile = __DIR__ . '/../../sessions/telegram_bot.madeline';
        
        try {
            // Initialize MadelineProto
            $settings = new Settings;
            $settings->setAppInfo((new AppInfo)
                ->setApiId(getenv('TELEGRAM_API_ID') ?: 123456) // Replace with your API ID
                ->setApiHash(getenv('TELEGRAM_API_HASH') ?: 'yourapihash') // Replace with your API Hash
            );
            
            // Set logging level
            $settings->getLogger()->setLevel(Logger::LEVEL_ERROR);
            
            // Create API instance
            $this->api = new API($sessionFile, $settings);
            
            // Use the stored bot token from LinkManager
            $token = $this->linkManager->getTelegramToken();
            if (!empty($token)) {
                $this->api->botLogin($token);
            }
        } catch (\Throwable $e) {
            // Log the error
            error_log('TelegramBot initialization error: ' . $e->getMessage());
        }
    }
    
    /**
     * Set a new bot token
     */
    public function setBotToken(string $token): bool
    {
        try {
            if ($this->api) {
                $this->api->botLogin($token);
                $this->linkManager->setTelegramToken($token);
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            error_log('Error setting bot token: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get information about a channel
     */
    public function getChat(string $chatId): ?array
    {
        try {
            if (!$this->api) {
                return null;
            }
            
            // If the chat ID doesn't start with @ or - and isn't numeric, add @
            if (!str_starts_with($chatId, '@') && !str_starts_with($chatId, '-') && !is_numeric($chatId)) {
                $chatId = '@' . $chatId;
            }
            
            $result = $this->api->getInfo($chatId);
            return $result;
        } catch (\Throwable $e) {
            error_log('Error getting chat info: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send a message to a chat
     */
    public function sendMessage(string $chatId, string $text): bool
    {
        try {
            if (!$this->api) {
                return false;
            }
            
            $this->api->messages->sendMessage([
                'peer' => $chatId,
                'message' => $text
            ]);
            
            return true;
        } catch (\Throwable $e) {
            error_log('Error sending message: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Register a handler for private messages
     */
    public function registerPrivateMessageHandler(callable $handlerFunction): void
    {
        $this->messageHandlers[] = $handlerFunction;
    }
    
    /**
     * Start polling for updates
     */
    public function startPolling(): void
    {
        if (!$this->api) {
            error_log('Cannot start polling: API not initialized');
            return;
        }
        
        // Start a background task to handle updates
        try {
            $this->api->loop(function () {
                $this->api->setEventHandler(new class($this->messageHandlers) extends EventHandler {
                    private $handlers = [];
                    
                    public function __construct(array $handlers)
                    {
                        $this->handlers = $handlers;
                        parent::__construct();
                    }
                    
                    public function onUpdateNewMessage(array $update): void
                    {
                        // Check if this is a private message
                        $message = $update['message'] ?? null;
                        if (!$message || !isset($message['from_id'])) {
                            return;
                        }
                        
                        // Check if it's a private message (not from a channel or group)
                        if (isset($message['peer_id']['_']) && $message['peer_id']['_'] === 'peerUser') {
                            // Call all registered handlers
                            foreach ($this->handlers as $handler) {
                                call_user_func($handler, $message);
                            }
                        }
                    }
                });
            });
        } catch (\Throwable $e) {
            error_log('Error starting polling: ' . $e->getMessage());
        }
    }
    
    /**
     * Check channels for links
     */
    public function checkChannelsForLinks(int $maxChannels = 100): int
    {
        $totalNewLinks = 0;
        $channels = $this->linkManager->getChannels();
        $channels = array_slice($channels, 0, $maxChannels);
        
        foreach ($channels as $channel) {
            try {
                // Get channel information
                $channelInfo = $this->getChat($channel);
                if (!$channelInfo) {
                    continue;
                }
                
                // Get recent messages
                $messages = $this->api->messages->getHistory([
                    'peer' => $channelInfo['id'],
                    'limit' => 100
                ]);
                
                // Extract links from messages
                foreach ($messages['messages'] as $message) {
                    if (isset($message['message']) && !empty($message['message'])) {
                        $messageText = $message['message'];
                        
                        // Extract Telegram links
                        preg_match_all('/(https?:\/\/)?t\.me\/([a-zA-Z0-9_+\-]+)/', $messageText, $matches);
                        
                        if (!empty($matches[0])) {
                            foreach ($matches[0] as $link) {
                                $isNew = $this->linkManager->addLink($link, $channel, $messageText);
                                if ($isNew) {
                                    $totalNewLinks++;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('Error checking channel ' . $channel . ': ' . $e->getMessage());
            }
        }
        
        // Update last check time
        $this->linkManager->updateLastCheckTime();
        
        return $totalNewLinks;
    }
}