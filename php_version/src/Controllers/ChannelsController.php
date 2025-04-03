<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use App\Services\LinkManager;

class ChannelsController
{
    private PhpRenderer $renderer;
    private LinkManager $linkManager;
    
    public function __construct(PhpRenderer $renderer, LinkManager $linkManager)
    {
        $this->renderer = $renderer;
        $this->linkManager = $linkManager;
    }
    
    /**
     * View channels page
     */
    public function index(Request $request, Response $response): Response
    {
        $channels = $this->linkManager->getChannels();
        $websites = $this->linkManager->getWebsites();
        $categories = $this->linkManager->getCategories();
        
        // Get channel categories
        $channelCategories = [];
        foreach ($channels as $channel) {
            $channelCategories[$channel] = 'عمومی'; // Default category
            
            // Get actual category from LinkManager (if set)
            if (method_exists($this->linkManager, 'getChannelCategory')) {
                $channelCategories[$channel] = $this->linkManager->getChannelCategory($channel) ?? 'عمومی';
            }
        }
        
        // Get website categories
        $websiteCategories = [];
        foreach ($websites as $website) {
            $websiteCategories[$website] = 'عمومی'; // Default category
            
            // Get actual category from LinkManager (if set)
            if (method_exists($this->linkManager, 'getWebsiteCategory')) {
                $websiteCategories[$website] = $this->linkManager->getWebsiteCategory($website) ?? 'عمومی';
            }
        }
        
        // Pass data to the view
        $data = [
            'channels' => $channels,
            'websites' => $websites,
            'categories' => $categories,
            'channelCategories' => $channelCategories,
            'websiteCategories' => $websiteCategories,
            'scrollCount' => $this->linkManager->getScrollCount() ?? 10
        ];
        
        return $this->renderer->render($response, 'channels.php', $data);
    }
    
    /**
     * Add a channel
     */
    public function add(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        $type = $params['type'] ?? 'channel';
        $source = $params['source'] ?? '';
        $category = $params['category'] ?? 'عمومی';
        
        if (!empty($source)) {
            if ($type === 'channel') {
                $result = $this->linkManager->addChannel($source, $category);
            } else {
                $result = $this->linkManager->addWebsite($source, $category);
            }
            
            // Return JSON response for AJAX requests
            if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                $data = [
                    'success' => $result,
                    'message' => $result ? 'Added successfully' : 'Already exists or invalid'
                ];
                
                $payload = json_encode($data);
                $response->getBody()->write($payload);
                
                return $response->withHeader('Content-Type', 'application/json');
            }
        }
        
        // Redirect back to channels page
        return $response->withHeader('Location', '/channels')->withStatus(302);
    }
    
    /**
     * Remove a channel
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        $channel = $args['channel'] ?? '';
        $queryParams = $request->getQueryParams();
        $isWebsite = isset($queryParams['type']) && $queryParams['type'] === 'website';
        
        if (!empty($channel)) {
            if ($isWebsite) {
                $channel = urldecode($channel);
                $this->linkManager->removeWebsite($channel);
            } else {
                $this->linkManager->removeChannel($channel);
            }
        }
        
        // Redirect back to channels page
        return $response->withHeader('Location', '/channels')->withStatus(302);
    }
    
    /**
     * Update channel category
     */
    public function updateCategory(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        $channel = $params['channel'] ?? '';
        $category = $params['category'] ?? 'عمومی';
        $isWebsite = isset($params['is_website']) && $params['is_website'] === '1';
        
        if (!empty($channel)) {
            if ($isWebsite) {
                if (method_exists($this->linkManager, 'setWebsiteCategory')) {
                    $this->linkManager->setWebsiteCategory($channel, $category);
                }
            } else {
                if (method_exists($this->linkManager, 'setChannelCategory')) {
                    $this->linkManager->setChannelCategory($channel, $category);
                }
            }
        }
        
        // Redirect back to channels page
        return $response->withHeader('Location', '/channels')->withStatus(302);
    }
    
    /**
     * Update website scroll count
     */
    public function updateScrollCount(Request $request, Response $response): Response
    {
        $params = (array)$request->getParsedBody();
        $scrollCount = (int)($params['scroll_count'] ?? 10);
        
        $this->linkManager->setScrollCount($scrollCount);
        
        // Redirect back to channels page
        return $response->withHeader('Location', '/channels')->withStatus(302);
    }
}