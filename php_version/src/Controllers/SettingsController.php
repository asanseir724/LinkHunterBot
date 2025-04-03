<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use App\Services\LinkManager;
use App\Services\TelegramBot;
use App\Services\AvalaiApi;

class SettingsController
{
    private PhpRenderer $renderer;
    private LinkManager $linkManager;
    private TelegramBot $telegramBot;
    private AvalaiApi $avalaiApi;
    
    public function __construct(PhpRenderer $renderer, LinkManager $linkManager, AvalaiApi $avalaiApi)
    {
        $this->renderer = $renderer;
        $this->linkManager = $linkManager;
        $this->telegramBot = new TelegramBot($linkManager);
        $this->avalaiApi = $avalaiApi;
    }
    
    /**
     * View settings page
     */
    public function index(Request $request, Response $response): Response
    {
        $checkInterval = $this->linkManager->getCheckInterval();
        $telegramTokens = $this->linkManager->getAllTelegramTokens();
        $categories = $this->linkManager->getCategories();
        $categoryKeywords = $this->linkManager->getCategoryKeywords();
        
        $data = [
            'checkInterval' => $checkInterval,
            'telegramTokens' => $telegramTokens,
            'categories' => $categories,
            'categoryKeywords' => $categoryKeywords
        ];
        
        return $this->renderer->render($response, 'settings.php', $data);
    }
    
    /**
     * Update settings
     */
    public function update(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        
        // Update check interval
        if (isset($params['check_interval'])) {
            $checkInterval = (int)$params['check_interval'];
            $this->linkManager->setCheckInterval($checkInterval);
        }
        
        // Update Telegram tokens
        if (isset($params['telegram_token']) && !empty($params['telegram_token'])) {
            $this->telegramBot->setBotToken($params['telegram_token']);
        }
        
        // Add additional Telegram token
        if (isset($params['add_token']) && !empty($params['add_token'])) {
            $this->linkManager->addTelegramToken($params['add_token']);
        }
        
        // Remove Telegram token
        if (isset($params['remove_token']) && !empty($params['remove_token'])) {
            $this->linkManager->removeTelegramToken($params['remove_token']);
        }
        
        // Update category keywords
        if (isset($params['category_keywords']) && is_array($params['category_keywords'])) {
            foreach ($params['category_keywords'] as $category => $keywordsStr) {
                $keywords = array_filter(array_map('trim', explode(',', $keywordsStr)));
                
                if (!empty($keywords)) {
                    $this->linkManager->updateCategoryKeywords($category, $keywords);
                }
            }
        }
        
        // Redirect back to settings page
        return $response->withHeader('Location', '/settings')->withStatus(302);
    }
    
    /**
     * Trigger an immediate check
     */
    public function checkNow(Request $request, Response $response): Response
    {
        // Check channels for links
        $newLinks = $this->telegramBot->checkChannelsForLinks();
        
        // Update last check time
        $this->linkManager->updateLastCheckTime();
        
        // Check if this is an AJAX request
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $data = [
                'success' => true,
                'new_links' => $newLinks,
                'last_check_time' => date('Y-m-d H:i:s', $this->linkManager->getLastCheckTime())
            ];
            
            $payload = json_encode($data);
            $response->getBody()->write($payload);
            
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Redirect back to home page
        return $response->withHeader('Location', '/')->withStatus(302);
    }
    
    /**
     * View Avalai settings page
     */
    public function avalaiSettings(Request $request, Response $response): Response
    {
        $avalaiSettings = $this->avalaiApi->getSettings();
        $avalaiEnabled = $this->avalaiApi->isEnabled();
        $chatHistory = $this->avalaiApi->getChatHistory();
        
        $data = [
            'avalaiSettings' => $avalaiSettings,
            'avalaiEnabled' => $avalaiEnabled,
            'chatHistory' => $chatHistory
        ];
        
        return $this->renderer->render($response, 'avalai_settings.php', $data);
    }
    
    /**
     * Update Avalai settings
     */
    public function updateAvalaiSettings(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        
        $settings = [
            'enabled' => isset($params['enabled']) && $params['enabled'] === '1',
            'default_prompt' => $params['default_prompt'] ?? '',
            'answer_all_messages' => isset($params['answer_all_messages']) && $params['answer_all_messages'] === '1',
            'only_answer_questions' => isset($params['only_answer_questions']) && $params['only_answer_questions'] === '1'
        ];
        
        $this->avalaiApi->updateSettings($settings);
        
        // Handle adding sample messages
        if (isset($params['add_sample_messages']) && $params['add_sample_messages'] === '1') {
            $count = (int)($params['sample_count'] ?? 5);
            $this->avalaiApi->addSampleMessages($count);
        }
        
        // Redirect back to Avalai settings page
        return $response->withHeader('Location', '/avalai-settings')->withStatus(302);
    }
}