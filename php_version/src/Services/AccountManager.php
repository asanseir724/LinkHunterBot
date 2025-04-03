<?php

namespace App\Services;

use Psr\Log\LoggerInterface;

/**
 * Class AccountManager
 * Manages multiple Telegram user accounts
 */
class AccountManager
{
    private $accounts = [];
    private $data_file;
    private $logger;

    /**
     * AccountManager constructor
     * 
     * @param string $data_file Path to accounts data file
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(string $data_file = 'accounts_data.json', ?LoggerInterface $logger = null)
    {
        $this->data_file = $data_file;
        $this->logger = $logger;
        $this->loadAccounts();
    }

    /**
     * Load accounts from data file
     */
    public function loadAccounts(): void
    {
        if (file_exists($this->data_file)) {
            try {
                $data = json_decode(file_get_contents($this->data_file), true);
                
                if (!empty($data) && is_array($data)) {
                    foreach ($data as $accountData) {
                        $phone = $accountData['phone'] ?? null;
                        $name = $accountData['name'] ?? null;
                        
                        if ($phone) {
                            $account = new UserAccount($phone, $name, $this->logger);
                            $this->accounts[$phone] = $account;
                        }
                    }
                }
                
                $this->log('info', "Loaded " . count($this->accounts) . " accounts from storage");
            } catch (\Throwable $e) {
                $this->log('error', "Failed to load accounts: " . $e->getMessage());
            }
        }
    }

    /**
     * Save accounts to data file
     * 
     * @return bool True if successful
     */
    public function saveAccounts(): bool
    {
        try {
            $data = [];
            
            foreach ($this->accounts as $account) {
                $data[] = $account->toArray();
            }
            
            file_put_contents($this->data_file, json_encode($data, JSON_PRETTY_PRINT));
            $this->log('info', "Saved " . count($this->accounts) . " accounts to storage");
            
            return true;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to save accounts: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a new account
     * 
     * @param string $phone Phone number
     * @param string|null $name Optional name for this account
     * @return array Result of the operation
     */
    public function addAccount(string $phone, ?string $name = null): array
    {
        // Remove any non-numeric characters and ensure it starts with +
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add the plus sign if not present
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        // Check if account already exists
        if (isset($this->accounts[$phone])) {
            return [
                'success' => false,
                'error' => 'حساب کاربری با این شماره تلفن قبلاً اضافه شده است.'
            ];
        }
        
        try {
            $account = new UserAccount($phone, $name, $this->logger);
            
            // Start the login process
            $result = $account->startPhoneLogin();
            
            if ($result['success']) {
                // Store the account temporarily
                $this->accounts[$phone] = $account;
                $this->saveAccounts();
                
                return [
                    'success' => true,
                    'phone' => $phone,
                    'phone_code_hash' => $result['phone_code_hash'],
                    'type' => $result['type'],
                    'next_type' => $result['next_type'] ?? null,
                    'timeout' => $result['timeout'] ?? 60
                ];
            }
            
            return $result;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to add account: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify the code for an account
     * 
     * @param string $phone Phone number
     * @param string $code Verification code
     * @param string $phoneCodeHash Phone code hash
     * @return array Result of verification
     */
    public function verifyCode(string $phone, string $code, string $phoneCodeHash): array
    {
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        if (!isset($this->accounts[$phone])) {
            return [
                'success' => false,
                'error' => 'حساب کاربری یافت نشد.'
            ];
        }
        
        $account = $this->accounts[$phone];
        $result = $account->verifyCode($code, $phoneCodeHash);
        
        // If verification was successful, save the updated account state
        if ($result['success']) {
            $this->saveAccounts();
        }
        
        return $result;
    }

    /**
     * Verify 2FA password for an account
     * 
     * @param string $phone Phone number
     * @param string $password 2FA password
     * @return array Result of verification
     */
    public function verify2FA(string $phone, string $password): array
    {
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        if (!isset($this->accounts[$phone])) {
            return [
                'success' => false,
                'error' => 'حساب کاربری یافت نشد.'
            ];
        }
        
        $account = $this->accounts[$phone];
        $result = $account->verify2FA($password);
        
        // If verification was successful, save the updated account state
        if ($result['success']) {
            $this->saveAccounts();
        }
        
        return $result;
    }

    /**
     * Connect to a specific account
     * 
     * @param string $phone Phone number
     * @return array Connection result
     */
    public function connectAccount(string $phone): array
    {
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        if (!isset($this->accounts[$phone])) {
            return [
                'success' => false,
                'error' => 'حساب کاربری یافت نشد.'
            ];
        }
        
        $account = $this->accounts[$phone];
        
        if ($account->connect()) {
            $this->saveAccounts();
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'اتصال به حساب کاربری ناموفق بود.'
        ];
    }

    /**
     * Disconnect a specific account
     * 
     * @param string $phone Phone number
     * @return array Disconnection result
     */
    public function disconnectAccount(string $phone): array
    {
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        if (!isset($this->accounts[$phone])) {
            return [
                'success' => false,
                'error' => 'حساب کاربری یافت نشد.'
            ];
        }
        
        $account = $this->accounts[$phone];
        
        if ($account->disconnect()) {
            $this->saveAccounts();
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'قطع اتصال حساب کاربری ناموفق بود.'
        ];
    }

    /**
     * Remove an account
     * 
     * @param string $phone Phone number
     * @return array Removal result
     */
    public function removeAccount(string $phone): array
    {
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        if (!isset($this->accounts[$phone])) {
            return [
                'success' => false,
                'error' => 'حساب کاربری یافت نشد.'
            ];
        }
        
        try {
            // Disconnect first if connected
            $account = $this->accounts[$phone];
            $account->disconnect();
            
            // Remove session file if it exists
            $sessionPath = $account->getSessionPath();
            if (file_exists($sessionPath)) {
                unlink($sessionPath);
            }
            
            // Remove from accounts array
            unset($this->accounts[$phone]);
            $this->saveAccounts();
            
            return [
                'success' => true
            ];
        } catch (\Throwable $e) {
            $this->log('error', "Failed to remove account: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all accounts
     * 
     * @return array List of accounts
     */
    public function getAccounts(): array
    {
        $accounts = [];
        
        foreach ($this->accounts as $phone => $account) {
            $accounts[$phone] = $account->toArray();
        }
        
        return $accounts;
    }

    /**
     * Get a specific account
     * 
     * @param string $phone Phone number
     * @return UserAccount|null The account object or null if not found
     */
    public function getAccount(string $phone): ?UserAccount
    {
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }
        
        return $this->accounts[$phone] ?? null;
    }

    /**
     * Count connected accounts
     * 
     * @return int Number of connected accounts
     */
    public function countConnectedAccounts(): int
    {
        $count = 0;
        
        foreach ($this->accounts as $account) {
            if ($account->isConnected()) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Check all accounts for links
     * 
     * @param int $messagesLimit Maximum number of messages to check per account
     * @return array Results for each account
     */
    public function checkAllAccountsForLinks(int $messagesLimit = 1000): array
    {
        $results = [];
        $totalLinks = 0;
        
        foreach ($this->accounts as $phone => $account) {
            if ($account->isConnected()) {
                $result = $account->extractLinks($messagesLimit);
                
                if ($result['success']) {
                    $linkCount = count($result['links'] ?? []);
                    $totalLinks += $linkCount;
                    
                    $results[$phone] = [
                        'success' => true,
                        'links_count' => $linkCount,
                        'links' => $result['links']
                    ];
                    
                    // Update last check time
                    $account->updateLastCheckTime();
                } else {
                    $results[$phone] = [
                        'success' => false,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }
            } else {
                $results[$phone] = [
                    'success' => false,
                    'error' => 'Account not connected'
                ];
            }
        }
        
        // Save updated last check times
        $this->saveAccounts();
        
        return [
            'success' => true,
            'total_links' => $totalLinks,
            'accounts' => $results
        ];
    }

    /**
     * Get message history for all connected accounts
     * 
     * @param int $limit Maximum messages per account
     * @return array Messages grouped by account
     */
    public function getAllPrivateMessages(int $limit = 100): array
    {
        $results = [];
        
        foreach ($this->accounts as $phone => $account) {
            if ($account->isConnected()) {
                $dialogs = $account->getDialogs($limit);
                
                if (!empty($dialogs['success']) && !empty($dialogs['dialogs'])) {
                    $privateMessages = [];
                    
                    foreach ($dialogs['dialogs'] as $peer => $dialog) {
                        try {
                            $peerInfo = $account->getMadelineProto()->getInfo($peer);
                            $peerType = $peerInfo['type'];
                            
                            // Only include user dialogs (private messages)
                            if ($peerType === 'user') {
                                $username = $peerInfo['User']['username'] ?? null;
                                $firstName = $peerInfo['User']['first_name'] ?? null;
                                $lastName = $peerInfo['User']['last_name'] ?? null;
                                
                                $displayName = $username ? '@' . $username : ($firstName ? $firstName . ' ' . ($lastName ?? '') : 'Unknown');
                                
                                $messages = $account->getMessages($peer, $limit);
                                
                                if (!empty($messages['success']) && !empty($messages['messages']['messages'])) {
                                    $formattedMessages = [];
                                    
                                    foreach ($messages['messages']['messages'] as $message) {
                                        if (isset($message['message'])) {
                                            $isSelf = isset($message['out']) && $message['out'];
                                            
                                            $formattedMessages[] = [
                                                'id' => $message['id'] ?? null,
                                                'date' => $message['date'] ?? null,
                                                'text' => $message['message'] ?? '',
                                                'is_outgoing' => $isSelf,
                                                'sender_name' => $isSelf ? 'شما' : $displayName
                                            ];
                                        }
                                    }
                                    
                                    // Sort by date - newest first
                                    usort($formattedMessages, function($a, $b) {
                                        return ($b['date'] ?? 0) <=> ($a['date'] ?? 0);
                                    });
                                    
                                    $privateMessages[$displayName] = [
                                        'peer_id' => $peer,
                                        'name' => $displayName,
                                        'username' => $username,
                                        'first_name' => $firstName,
                                        'last_name' => $lastName,
                                        'messages' => $formattedMessages
                                    ];
                                }
                            }
                        } catch (\Throwable $e) {
                            continue;
                        }
                    }
                    
                    $results[$phone] = [
                        'success' => true,
                        'account_info' => $account->toArray(),
                        'private_messages' => $privateMessages
                    ];
                }
            }
        }
        
        return $results;
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
            $this->logger->$level("[AccountManager] {$message}");
        }
    }
}