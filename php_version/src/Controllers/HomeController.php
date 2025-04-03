<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use App\Services\LinkManager;

class HomeController
{
    private PhpRenderer $renderer;
    private LinkManager $linkManager;
    
    public function __construct(PhpRenderer $renderer, LinkManager $linkManager)
    {
        $this->renderer = $renderer;
        $this->linkManager = $linkManager;
    }
    
    /**
     * Home page
     */
    public function index(Request $request, Response $response): Response
    {
        $totalLinks = count($this->linkManager->getAllLinks());
        $newLinks = count($this->linkManager->getNewLinks());
        $lastCheckTime = $this->linkManager->getLastCheckTime();
        $checkInterval = $this->linkManager->getCheckInterval();
        
        // Calculate next check time
        $nextCheckTime = null;
        if ($lastCheckTime) {
            $nextCheckTime = $lastCheckTime + ($checkInterval * 60);
        }
        
        // Get stats by category
        $linksByCategory = $this->linkManager->getLinksByCategory();
        $categoryStats = [];
        
        foreach ($linksByCategory as $category => $links) {
            $categoryStats[$category] = count($links);
        }
        
        // Pass data to the view
        $data = [
            'totalLinks' => $totalLinks,
            'newLinks' => $newLinks,
            'lastCheckTime' => $lastCheckTime ? date('Y-m-d H:i:s', $lastCheckTime) : null,
            'nextCheckTime' => $nextCheckTime ? date('Y-m-d H:i:s', $nextCheckTime) : null,
            'checkInterval' => $checkInterval,
            'categoryStats' => $categoryStats
        ];
        
        return $this->renderer->render($response, 'home.php', $data);
    }
}