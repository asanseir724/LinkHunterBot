<?php

namespace App\Services;

class AccountManager
{
    private string $accountsDataFile;
    private array $accounts = [];
    private LinkManager $linkManager;
    
    public function __construct(LinkManager $linkManager)
    {
        $this->accountsDataFile = __DIR__ . '/../../accounts_data.json';
        $this->linkManager = $linkManager;
        $this->loadAccounts();
    }
    
    private function loadAccounts(): void
    {
        if (file_exists($this->accountsDataFile)) {
            $accountsData = json_decode(file_get_contents($this->accountsDataFile), true) ?: [];
            
            // Create UserAccount objects for each account
            foreach ($accountsData as $accountData) {
                $phone = $accountData['phone'] ?? '';
                if ($phone) {
                    $this->accounts[$phone] = new UserAccount($phone, $this->linkManager);
                }
            }
        } else {
            file_put_contents($this->accountsDataFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    /**
     * Add a new account
     */
    public function addAccount(string $phone): array
    {
        // Normalize phone number
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Check if account already exists
        if (isset($this->accounts[$phone])) {
            return [
                'success' => false,
                'message' => 'Account already exists',
                'phone' => $phone
            ];
        }
        
        // Create a new account
        $account = new UserAccount($phone, $this->linkManager);
        $this->accounts[$phone] = $account;
        
        return [
            'success' => true,
            'message' => 'Account added',
            'phone' => $phone
        ];
    }
    
    /**
     * Remove an account
     */
    public function removeAccount(string $phone): bool
    {
        if (isset($this->accounts[$phone])) {
            // Disconnect the account first
            $this->accounts[$phone]->disconnect();
            
            // Remove from accounts list
            unset($this->accounts[$phone]);
            
            // Update accounts data file
            $this->saveAccountsData();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all accounts
     */
    public function getAccounts(): array
    {
        $accountsData = [];
        
        foreach ($this->accounts as $phone => $account) {
            $accountsData[] = $account->getAccountData();
        }
        
        return $accountsData;
    }
    
    /**
     * Get a specific account
     */
    public function getAccount(string $phone): ?UserAccount
    {
        return $this->accounts[$phone] ?? null;
    }
    
    /**
     * Connect an account
     */
    public function connectAccount(string $phone): array
    {
        if (isset($this->accounts[$phone])) {
            return $this->accounts[$phone]->connect();
        }
        
        return [
            'success' => false,
            'message' => 'Account not found',
            'status' => 'error'
        ];
    }
    
    /**
     * Disconnect an account
     */
    public function disconnectAccount(string $phone): bool
    {
        if (isset($this->accounts[$phone])) {
            return $this->accounts[$phone]->disconnect();
        }
        
        return false;
    }
    
    /**
     * Verify code for an account
     */
    public function verifyCode(string $phone, string $code, string $phoneCodeHash): array
    {
        if (isset($this->accounts[$phone])) {
            return $this->accounts[$phone]->verifyCode($code, $phoneCodeHash);
        }
        
        return [
            'success' => false,
            'message' => 'Account not found',
            'status' => 'error'
        ];
    }
    
    /**
     * Verify 2FA for an account
     */
    public function verify2FA(string $phone, string $password): array
    {
        if (isset($this->accounts[$phone])) {
            return $this->accounts[$phone]->verify2FA($password);
        }
        
        return [
            'success' => false,
            'message' => 'Account not found',
            'status' => 'error'
        ];
    }
    
    /**
     * Check all connected accounts for links
     */
    public function checkAllAccountsForLinks(): array
    {
        $results = [];
        $totalNewLinks = 0;
        
        foreach ($this->accounts as $phone => $account) {
            if ($account->isConnected()) {
                $result = $account->checkForLinks();
                $results[$phone] = $result;
                
                if ($result['success']) {
                    $totalNewLinks += $result['new_links'];
                }
            }
        }
        
        return [
            'success' => true,
            'message' => "Found $totalNewLinks new links from " . count($results) . " account(s)",
            'results' => $results,
            'total_new_links' => $totalNewLinks
        ];
    }
    
    /**
     * Get private messages from all connected accounts
     */
    public function getAllPrivateMessages(int $limit = 50): array
    {
        $allMessages = [];
        
        foreach ($this->accounts as $phone => $account) {
            if ($account->isConnected()) {
                $result = $account->getPrivateMessages($limit);
                
                if ($result['success']) {
                    $accountData = $account->getAccountData();
                    $allMessages[$phone] = [
                        'account' => [
                            'phone' => $phone,
                            'username' => $accountData['username'] ?? '',
                            'first_name' => $accountData['first_name'] ?? '',
                            'last_name' => $accountData['last_name'] ?? ''
                        ],
                        'chats' => $result['messages']
                    ];
                }
            }
        }
        
        return $allMessages;
    }
    
    /**
     * Send a message to a user from a specific account
     */
    public function sendMessage(string $phone, string $userId, string $text): bool
    {
        if (isset($this->accounts[$phone])) {
            return $this->accounts[$phone]->sendMessage($userId, $text);
        }
        
        return false;
    }
    
    /**
     * Save accounts data to file
     */
    private function saveAccountsData(): void
    {
        $accountsData = [];
        
        foreach ($this->accounts as $phone => $account) {
            $accountsData[] = $account->getAccountData();
        }
        
        file_put_contents($this->accountsDataFile, json_encode($accountsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}