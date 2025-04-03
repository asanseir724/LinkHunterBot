<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\APIWrapper;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Exception;
use danog\MadelineProto\RPCErrorException;
use Psr\Log\LoggerInterface;

/**
 * Class UserAccount
 * Handles Telegram user account functionality using MadelineProto
 */
class UserAccount
{
    private $phone;
    private $name;
    private $session_path;
    private $madelineProto;
    private $logger;
    private $connected = false;
    private $last_check_time = null;
    private $user_info = null;

    /**
     * UserAccount constructor
     * 
     * @param string $phone Phone number in international format
     * @param string|null $name Optional name for this account
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(string $phone, ?string $name = null, ?LoggerInterface $logger = null)
    {
        $this->phone = $phone;
        $this->name = $name ?: $phone;
        $this->logger = $logger;
        $this->session_path = $this->getSessionPath();
        
        $this->log('info', "Creating UserAccount instance for {$this->phone}");
    }

    /**
     * Get the path to the session file for this account
     * 
     * @return string Session file path
     */
    public function getSessionPath(): string
    {
        // Normalize phone number by removing + and spaces
        $normalized = preg_replace('/[^0-9]/', '', $this->phone);
        $storage_path = __DIR__ . '/../../storage/sessions';
        
        // Create storage directory if it doesn't exist
        if (!is_dir($storage_path)) {
            mkdir($storage_path, 0755, true);
        }
        
        return $storage_path . '/user_' . $normalized . '.madeline';
    }
    
    /**
     * Get the MadelineProto instance
     * 
     * @return API|null MadelineProto instance or null if not initialized
     */
    public function getMadelineProto(): ?API
    {
        return $this->madelineProto;
    }

    /**
     * Initialize MadelineProto client
     * 
     * @return bool True if successful, false otherwise
     */
    public function init(): bool
    {
        try {
            $settings = new Settings;
            
            // MadelineProto has default app credentials, no need to provide API ID and Hash
            $this->madelineProto = new API($this->session_path, $settings);
            $this->log('info', "Initialized MadelineProto for {$this->phone}");
            return true;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to initialize MadelineProto: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Start phone login process
     * 
     * @return array Login status
     */
    public function startPhoneLogin(): array
    {
        if (!$this->madelineProto) {
            if (!$this->init()) {
                return [
                    'success' => false,
                    'error' => 'Failed to initialize MadelineProto'
                ];
            }
        }
        
        try {
            $this->log('info', "Starting phone login for {$this->phone}");
            $sentCode = $this->madelineProto->phoneLogin($this->phone);
            
            return [
                'success' => true,
                'phone_code_hash' => $sentCode['phone_code_hash'],
                'type' => $sentCode['type'],
                'next_type' => $sentCode['next_type'] ?? null,
                'timeout' => $sentCode['timeout'] ?? 60
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Failed to start phone login: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify received phone code
     * 
     * @param string $code Verification code
     * @param string $phoneCodeHash Phone code hash received from startPhoneLogin
     * @return array Verification result
     */
    public function verifyCode(string $code, string $phoneCodeHash): array
    {
        if (!$this->madelineProto) {
            if (!$this->init()) {
                return [
                    'success' => false,
                    'error' => 'Failed to initialize MadelineProto'
                ];
            }
        }
        
        try {
            $this->log('info', "Verifying code for {$this->phone}");
            $result = $this->madelineProto->completePhoneLogin($code);
            
            if ($result['_'] === 'account.password') {
                // 2FA is enabled
                return [
                    'success' => true,
                    'requires_2fa' => true,
                    'hint' => $result['hint'] ?? '',
                    'has_recovery' => !empty($result['has_recovery'])
                ];
            }
            
            // Login completed successfully
            $this->connected = true;
            $this->getUserInfo();
            $this->updateLastCheckTime();
            
            return [
                'success' => true,
                'requires_2fa' => false
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Code verification failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Complete 2FA verification with password
     * 
     * @param string $password Two-factor authentication password
     * @return array Verification result
     */
    public function verify2FA(string $password): array
    {
        if (!$this->madelineProto) {
            if (!$this->init()) {
                return [
                    'success' => false,
                    'error' => 'Failed to initialize MadelineProto'
                ];
            }
        }
        
        try {
            $this->log('info', "Verifying 2FA for {$this->phone}");
            $this->madelineProto->complete2faLogin($password);
            
            // Login completed successfully
            $this->connected = true;
            $this->getUserInfo();
            $this->updateLastCheckTime();
            
            return [
                'success' => true
            ];
        } catch (RPCErrorException $e) {
            if (strpos($e->getMessage(), 'PASSWORD_HASH_INVALID') !== false) {
                return [
                    'success' => false,
                    'error' => 'رمز عبور نادرست است. لطفاً مجدداً تلاش کنید.'
                ];
            }
            
            $this->log('error', "2FA verification failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Throwable $e) {
            $this->log('error', "2FA verification failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if the account is connected
     * 
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        if (!$this->madelineProto) {
            if (!$this->init()) {
                return false;
            }
        }
        
        try {
            $this->connected = $this->madelineProto->getAuthorization() === 3;
            return $this->connected;
        } catch (\Throwable $e) {
            $this->log('warning', "Failed to check connection status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reconnect to Telegram
     * 
     * @return bool True if successful
     */
    public function connect(): bool
    {
        if (!$this->madelineProto) {
            if (!$this->init()) {
                return false;
            }
        }
        
        try {
            if ($this->isConnected()) {
                $this->log('info', "Already connected for {$this->phone}");
                return true;
            }
            
            $this->madelineProto->start();
            $this->connected = $this->madelineProto->getAuthorization() === 3;
            
            if ($this->connected) {
                $this->getUserInfo();
                $this->log('info', "Successfully connected account {$this->phone}");
            }
            
            return $this->connected;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to connect: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disconnect from Telegram
     * 
     * @return bool True if successful
     */
    public function disconnect(): bool
    {
        if (!$this->madelineProto) {
            return true; // Already disconnected
        }
        
        try {
            $this->log('info', "Disconnecting account {$this->phone}");
            $this->madelineProto->logout();
            $this->connected = false;
            return true;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to disconnect: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user information
     * 
     * @return array|null User information or null if failed
     */
    public function getUserInfo(): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }
        
        try {
            $this->user_info = $this->madelineProto->getSelf();
            return $this->user_info;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to get user info: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Join a chat using an invite link
     * 
     * @param string $link Invite link
     * @return array Join result
     */
    public function joinChat(string $link): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Account not connected'
            ];
        }
        
        try {
            $this->log('info', "Joining chat with link {$link}");
            $result = $this->madelineProto->joinChannelByLink($link);
            
            return [
                'success' => true,
                'chat_info' => $result
            ];
        } catch (RPCErrorException $e) {
            // Handle common errors
            if (strpos($e->getMessage(), 'INVITE_REQUEST_SENT') !== false) {
                return [
                    'success' => true,
                    'pending' => true,
                    'message' => 'درخواست عضویت ارسال شد. منتظر تأیید ادمین هستید.'
                ];
            } elseif (strpos($e->getMessage(), 'FLOOD_WAIT_') !== false) {
                preg_match('/FLOOD_WAIT_(\d+)/', $e->getMessage(), $matches);
                $wait_time = $matches[1] ?? 3600;
                
                return [
                    'success' => false,
                    'error' => "محدودیت زمانی تلگرام. لطفاً {$wait_time} ثانیه صبر کنید.",
                    'flood_wait' => (int)$wait_time
                ];
            } elseif (strpos($e->getMessage(), 'CHANNELS_TOO_MUCH') !== false) {
                return [
                    'success' => false,
                    'error' => 'شما در تعداد زیادی کانال عضو هستید. لطفاً ابتدا از برخی کانال‌ها خارج شوید.'
                ];
            } elseif (strpos($e->getMessage(), 'USER_ALREADY_PARTICIPANT') !== false) {
                return [
                    'success' => true,
                    'already_joined' => true,
                    'message' => 'شما قبلاً در این چت عضو شده‌اید.'
                ];
            }
            
            $this->log('error', "Failed to join chat: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Failed to join chat: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get messages from a chat
     * 
     * @param mixed $peer Chat/channel ID or username
     * @param int $limit Maximum number of messages to retrieve
     * @return array Messages
     */
    public function getMessages($peer, int $limit = 100): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Account not connected'
            ];
        }
        
        try {
            $messages = $this->madelineProto->messages->getHistory([
                'peer' => $peer, 
                'limit' => $limit,
                'offset_id' => 0,
                'offset_date' => 0,
                'add_offset' => 0,
                'max_id' => 0,
                'min_id' => 0,
                'hash' => 0
            ]);
            
            return [
                'success' => true,
                'messages' => $messages
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Failed to get messages: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get private chat messages (dialogs)
     * 
     * @param int $limit Maximum number of chats to retrieve
     * @return array Chats with their last messages
     */
    public function getDialogs(int $limit = 100): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Account not connected'
            ];
        }
        
        try {
            $dialogs = $this->madelineProto->getDialogs($limit);
            
            return [
                'success' => true,
                'dialogs' => $dialogs
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Failed to get dialogs: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extract links from messages in chats/channels
     * 
     * @param int $limit Maximum number of messages to check
     * @return array Found links
     */
    public function extractLinks(int $limit = 1000): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Account not connected'
            ];
        }
        
        try {
            $this->log('info', "Extracting links from chats for {$this->phone}");
            $dialogs = $this->madelineProto->getDialogs();
            $links = [];
            
            foreach ($dialogs as $peer => $dialog) {
                try {
                    $peerInfo = $this->madelineProto->getInfo($peer);
                    $peerType = $peerInfo['type'];
                    
                    // Skip non-channels and non-chats
                    if (!in_array($peerType, ['channel', 'chat', 'supergroup'])) {
                        continue;
                    }
                    
                    $peerName = $peerInfo['Chat']['title'] ?? ($peerInfo['User']['username'] ?? 'Unknown');
                    $this->log('info', "Checking messages in {$peerType} {$peerName}");
                    
                    $messages = $this->madelineProto->messages->getHistory([
                        'peer' => $peer, 
                        'limit' => $limit,
                        'offset_id' => 0,
                        'offset_date' => 0,
                        'add_offset' => 0,
                        'max_id' => 0,
                        'min_id' => 0,
                        'hash' => 0
                    ]);
                    
                    foreach ($messages['messages'] as $message) {
                        if (isset($message['message'])) {
                            $text = $message['message'];
                            
                            // Find Telegram links using regex
                            preg_match_all('/(https?:\/\/)?t(elegram)?\.me\/([^\s]+)/i', $text, $matches);
                            
                            if (!empty($matches[0])) {
                                foreach ($matches[0] as $link) {
                                    $links[] = [
                                        'link' => $link,
                                        'source' => $peerName,
                                        'source_type' => $peerType,
                                        'text' => $text
                                    ];
                                }
                            }
                            
                            // Find joinchat links
                            preg_match_all('/(https?:\/\/)?t(elegram)?\.me\/joinchat\/([^\s]+)/i', $text, $matches);
                            
                            if (!empty($matches[0])) {
                                foreach ($matches[0] as $link) {
                                    $links[] = [
                                        'link' => $link,
                                        'source' => $peerName,
                                        'source_type' => $peerType,
                                        'text' => $text
                                    ];
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->log('warning', "Failed to process peer: " . $e->getMessage());
                    continue;
                }
            }
            
            // Update last check time
            $this->updateLastCheckTime();
            
            return [
                'success' => true,
                'links' => $links
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Failed to extract links: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send a message to a user/chat
     * 
     * @param mixed $peer Recipient ID or username
     * @param string $text Message text
     * @return array Send result
     */
    public function sendMessage($peer, string $text): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'error' => 'Account not connected'
            ];
        }
        
        try {
            $result = $this->madelineProto->messages->sendMessage([
                'peer' => $peer,
                'message' => $text,
                'parse_mode' => 'HTML'
            ]);
            
            return [
                'success' => true,
                'message_id' => $result
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Failed to send message: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update the last check time
     */
    public function updateLastCheckTime(): void
    {
        $this->last_check_time = time();
    }
    
    /**
     * Get phone number
     * 
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }
    
    /**
     * Get account name
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Set account name
     * 
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    
    /**
     * Get last check time
     * 
     * @return int|null
     */
    public function getLastCheckTime(): ?int
    {
        return $this->last_check_time;
    }
    
    /**
     * Convert account to array for storage
     * 
     * @return array
     */
    public function toArray(): array
    {
        $user_info = $this->getUserInfo();
        
        return [
            'phone' => $this->phone,
            'name' => $this->name,
            'connected' => $this->isConnected(),
            'last_check_time' => $this->last_check_time,
            'first_name' => $user_info['first_name'] ?? null,
            'last_name' => $user_info['last_name'] ?? null,
            'username' => $user_info['username'] ?? null,
            'photo' => null, // Photo processing would be added separately
            'session_path' => $this->session_path
        ];
    }
    
    /**
     * Log a message
     * 
     * @param string $level Log level
     * @param string $message Message to log
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->$level("[UserAccount] {$message}");
        }
    }
}