<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings\Auth;

class UserAccount
{
    private string $phone;
    private ?API $api = null;
    private string $sessionPath;
    private array $accountData;
    private string $accountsDataFile;
    private LinkManager $linkManager;
    
    public function __construct(string $phone, LinkManager $linkManager)
    {
        $this->phone = $phone;
        $this->linkManager = $linkManager;
        $this->sessionPath = __DIR__ . '/../../sessions/' . preg_replace('/[^\d]/', '', $phone) . '.madeline';
        $this->accountsDataFile = __DIR__ . '/../../accounts_data.json';
        $this->loadAccountData();
        
        try {
            // Initialize MadelineProto settings
            $settings = new Settings;
            $settings->setAppInfo((new AppInfo)
                ->setApiId(getenv('TELEGRAM_API_ID') ?: 123456) // Replace with your API ID
                ->setApiHash(getenv('TELEGRAM_API_HASH') ?: 'yourapihash') // Replace with your API Hash
            );
            
            // Set logging level
            $settings->getLogger()->setLevel(Logger::LEVEL_ERROR);
            
            // Set authentication settings
            $settings->setAuth((new Auth)
                ->setTermsOfService(true)
            );
            
            // Create API instance if session file exists
            if (file_exists($this->sessionPath)) {
                $this->api = new API($this->sessionPath, $settings);
            }
        } catch (\Throwable $e) {
            error_log('UserAccount initialization error: ' . $e->getMessage());
        }
    }
    
    private function loadAccountData(): void
    {
        $defaultAccountData = [
            'phone' => $this->phone,
            'connected' => false,
            'first_name' => '',
            'last_name' => '',
            'username' => '',
            'user_id' => null,
            'is_bot' => false,
            'last_check_time' => null,
            'last_messages' => []
        ];
        
        if (file_exists($this->accountsDataFile)) {
            $accountsData = json_decode(file_get_contents($this->accountsDataFile), true) ?: [];
            
            // Find this account's data
            $accountData = null;
            foreach ($accountsData as $account) {
                if ($account['phone'] === $this->phone) {
                    $accountData = $account;
                    break;
                }
            }
            
            $this->accountData = $accountData ?? $defaultAccountData;
        } else {
            $this->accountData = $defaultAccountData;
            file_put_contents($this->accountsDataFile, json_encode([$this->accountData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    private function saveAccountData(): void
    {
        $accountsData = [];
        
        if (file_exists($this->accountsDataFile)) {
            $accountsData = json_decode(file_get_contents($this->accountsDataFile), true) ?: [];
            
            // Update this account's data
            $found = false;
            foreach ($accountsData as $key => $account) {
                if ($account['phone'] === $this->phone) {
                    $accountsData[$key] = $this->accountData;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $accountsData[] = $this->accountData;
            }
        } else {
            $accountsData[] = $this->accountData;
        }
        
        file_put_contents($this->accountsDataFile, json_encode($accountsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Check if the account is connected
     */
    public function isConnected(): bool
    {
        return $this->api !== null && $this->accountData['connected'];
    }
    
    /**
     * Start the connection process
     */
    public function connect(): array
    {
        if ($this->isConnected()) {
            return [
                'success' => true,
                'message' => 'Already connected',
                'status' => 'connected'
            ];
        }
        
        try {
            // Initialize MadelineProto settings
            $settings = new Settings;
            $settings->setAppInfo((new AppInfo)
                ->setApiId(getenv('TELEGRAM_API_ID') ?: 123456) // Replace with your API ID
                ->setApiHash(getenv('TELEGRAM_API_HASH') ?: 'yourapihash') // Replace with your API Hash
            );
            
            // Set logging level
            $settings->getLogger()->setLevel(Logger::LEVEL_ERROR);
            
            // Create API instance
            $this->api = new API($this->sessionPath, $settings);
            
            // Start the login process
            $sentCode = $this->api->phoneLogin($this->phone);
            
            return [
                'success' => true,
                'message' => 'Verification code sent',
                'status' => 'code_sent',
                'phone_code_hash' => $sentCode['phone_code_hash'],
                'type' => $sentCode['type']['_']
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
    
    /**
     * Complete the verification code step
     */
    public function verifyCode(string $code, string $phoneCodeHash): array
    {
        if (!$this->api) {
            return [
                'success' => false,
                'message' => 'Not connected',
                'status' => 'error'
            ];
        }
        
        try {
            $result = $this->api->completePhoneLogin($code);
            
            if ($result['_'] === 'auth.authorizationSignUpRequired') {
                // Registration required
                return [
                    'success' => true,
                    'message' => 'Registration required',
                    'status' => 'registration_required'
                ];
            } else if ($result['_'] === 'account.password') {
                // 2FA required
                return [
                    'success' => true,
                    'message' => '2FA required',
                    'status' => '2fa_required',
                    'hint' => $result['hint'] ?? ''
                ];
            } else {
                // Successfully logged in
                $this->updateAccountInfo();
                
                return [
                    'success' => true,
                    'message' => 'Successfully logged in',
                    'status' => 'logged_in',
                    'user_info' => $this->accountData
                ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Verification error: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
    
    /**
     * Complete the 2FA step
     */
    public function verify2FA(string $password): array
    {
        if (!$this->api) {
            return [
                'success' => false,
                'message' => 'Not connected',
                'status' => 'error'
            ];
        }
        
        try {
            $result = $this->api->complete2faLogin($password);
            
            // Successfully logged in with 2FA
            $this->updateAccountInfo();
            
            return [
                'success' => true,
                'message' => 'Successfully logged in with 2FA',
                'status' => 'logged_in',
                'user_info' => $this->accountData
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => '2FA error: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
    
    /**
     * Register a new account (if needed)
     */
    public function register(string $firstName, string $lastName = ''): array
    {
        if (!$this->api) {
            return [
                'success' => false,
                'message' => 'Not connected',
                'status' => 'error'
            ];
        }
        
        try {
            $result = $this->api->completeSignUp($firstName, $lastName);
            $this->updateAccountInfo();
            
            return [
                'success' => true,
                'message' => 'Successfully registered',
                'status' => 'registered',
                'user_info' => $this->accountData
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Registration error: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
    
    /**
     * Update account information after successful login
     */
    private function updateAccountInfo(): void
    {
        try {
            // Get user info
            $userInfo = $this->api->getSelf();
            
            $this->accountData['connected'] = true;
            $this->accountData['first_name'] = $userInfo['first_name'] ?? '';
            $this->accountData['last_name'] = $userInfo['last_name'] ?? '';
            $this->accountData['username'] = $userInfo['username'] ?? '';
            $this->accountData['user_id'] = $userInfo['id'];
            $this->accountData['is_bot'] = $userInfo['bot'] ?? false;
            
            $this->saveAccountData();
        } catch (\Throwable $e) {
            error_log('Error updating account info: ' . $e->getMessage());
        }
    }
    
    /**
     * Disconnect the account
     */
    public function disconnect(): bool
    {
        $this->accountData['connected'] = false;
        $this->saveAccountData();
        
        try {
            if (file_exists($this->sessionPath)) {
                unlink($this->sessionPath);
            }
            return true;
        } catch (\Throwable $e) {
            error_log('Error disconnecting account: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get account data
     */
    public function getAccountData(): array
    {
        return $this->accountData;
    }
    
    /**
     * Check joined groups and channels for links
     */
    public function checkForLinks(): array
    {
        if (!$this->isConnected() || !$this->api) {
            return [
                'success' => false,
                'message' => 'Not connected',
                'new_links' => 0
            ];
        }
        
        try {
            $totalNewLinks = 0;
            
            // Get all dialogs (chats, groups, channels)
            $dialogs = $this->api->getDialogs();
            
            foreach ($dialogs as $dialog) {
                try {
                    // Get information about this chat
                    $chatInfo = $this->api->getInfo($dialog);
                    $chatType = $chatInfo['type'] ?? '';
                    
                    // Only check groups and channels
                    if ($chatType === 'chat' || $chatType === 'supergroup' || $chatType === 'channel') {
                        // Get recent messages
                        $messages = $this->api->messages->getHistory([
                            'peer' => $dialog,
                            'limit' => 50
                        ]);
                        
                        $chatName = $chatInfo['Chat']['title'] ?? $chatInfo['title'] ?? 'Unknown';
                        
                        // Extract links from messages
                        foreach ($messages['messages'] as $message) {
                            if (isset($message['message']) && !empty($message['message'])) {
                                $messageText = $message['message'];
                                
                                // Extract Telegram links
                                preg_match_all('/(https?:\/\/)?t\.me\/([a-zA-Z0-9_+\-]+)/', $messageText, $matches);
                                
                                if (!empty($matches[0])) {
                                    foreach ($matches[0] as $link) {
                                        $isNew = $this->linkManager->addLink($link, $chatName, $messageText);
                                        if ($isNew) {
                                            $totalNewLinks++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('Error checking dialog: ' . $e->getMessage());
                    continue;
                }
            }
            
            // Update last check time
            $this->accountData['last_check_time'] = time();
            $this->saveAccountData();
            
            return [
                'success' => true,
                'message' => "Found $totalNewLinks new links",
                'new_links' => $totalNewLinks
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error checking for links: ' . $e->getMessage(),
                'new_links' => 0
            ];
        }
    }
    
    /**
     * Get private messages for the account
     */
    public function getPrivateMessages(int $limit = 50): array
    {
        if (!$this->isConnected() || !$this->api) {
            return [
                'success' => false,
                'message' => 'Not connected',
                'messages' => []
            ];
        }
        
        try {
            $privateMessages = [];
            
            // Get all dialogs (chats, groups, channels)
            $dialogs = $this->api->getDialogs();
            
            foreach ($dialogs as $dialog) {
                try {
                    // Get information about this chat
                    $chatInfo = $this->api->getInfo($dialog);
                    $chatType = $chatInfo['type'] ?? '';
                    
                    // Only check private chats
                    if ($chatType === 'user') {
                        // Get recent messages
                        $messages = $this->api->messages->getHistory([
                            'peer' => $dialog,
                            'limit' => $limit
                        ]);
                        
                        $userData = $chatInfo['User'] ?? [];
                        $userId = $userData['id'] ?? 0;
                        $username = $userData['username'] ?? null;
                        $firstName = $userData['first_name'] ?? '';
                        $lastName = $userData['last_name'] ?? '';
                        $displayName = $username ? '@' . $username : "$firstName $lastName";
                        
                        // Process messages
                        $chatMessages = [];
                        foreach ($messages['messages'] as $message) {
                            if (isset($message['message']) && !empty($message['message'])) {
                                $isOutgoing = $message['out'] ?? false;
                                
                                $chatMessages[] = [
                                    'message_id' => $message['id'],
                                    'text' => $message['message'],
                                    'date' => $message['date'],
                                    'is_outgoing' => $isOutgoing,
                                    'from_id' => $isOutgoing ? $this->accountData['user_id'] : $userId,
                                    'from_name' => $isOutgoing ? ($this->accountData['username'] ? '@' . $this->accountData['username'] : $this->accountData['first_name']) : $displayName
                                ];
                            }
                        }
                        
                        if (!empty($chatMessages)) {
                            $privateMessages[$userId] = [
                                'user_id' => $userId,
                                'username' => $username,
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'display_name' => $displayName,
                                'messages' => $chatMessages
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('Error checking dialog: ' . $e->getMessage());
                    continue;
                }
            }
            
            // Update last messages in account data
            $this->accountData['last_messages'] = $privateMessages;
            $this->saveAccountData();
            
            return [
                'success' => true,
                'message' => 'Retrieved private messages',
                'messages' => $privateMessages
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error getting private messages: ' . $e->getMessage(),
                'messages' => []
            ];
        }
    }
    
    /**
     * Send a message to a user
     */
    public function sendMessage(string $userId, string $text): bool
    {
        if (!$this->isConnected() || !$this->api) {
            return false;
        }
        
        try {
            $this->api->messages->sendMessage([
                'peer' => $userId,
                'message' => $text
            ]);
            
            return true;
        } catch (\Throwable $e) {
            error_log('Error sending message: ' . $e->getMessage());
            return false;
        }
    }
}