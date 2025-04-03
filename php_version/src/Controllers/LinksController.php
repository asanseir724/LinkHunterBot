<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use App\Services\LinkManager;

class LinksController
{
    private PhpRenderer $renderer;
    private LinkManager $linkManager;
    
    public function __construct(PhpRenderer $renderer, LinkManager $linkManager)
    {
        $this->renderer = $renderer;
        $this->linkManager = $linkManager;
    }
    
    /**
     * View links page
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $category = $queryParams['category'] ?? null;
        $showAll = isset($queryParams['all']) && $queryParams['all'] === '1';
        
        $links = [];
        $categories = $this->linkManager->getCategories();
        
        if ($showAll) {
            // Show all links
            if ($category) {
                // Filter by category
                $links = $this->linkManager->getLinksByCategory($category);
            } else {
                // Show all links without category filter
                $links = $this->linkManager->getAllLinks();
            }
        } else {
            // Show only new links
            if ($category) {
                // Filter new links by category
                $newLinks = $this->linkManager->getNewLinks();
                $categoryLinks = $this->linkManager->getLinksByCategory($category);
                $links = array_intersect($newLinks, $categoryLinks);
            } else {
                // Show all new links without category filter
                $links = $this->linkManager->getNewLinks();
            }
        }
        
        // Get category keywords
        $categoryKeywords = $this->linkManager->getCategoryKeywords($category);
        
        // Pass data to the view
        $data = [
            'links' => $links,
            'categories' => $categories,
            'selectedCategory' => $category,
            'showAll' => $showAll,
            'categoryKeywords' => $categoryKeywords
        ];
        
        return $this->renderer->render($response, 'links.php', $data);
    }
    
    /**
     * Clear links
     */
    public function clear(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $onlyNew = isset($queryParams['only_new']) && $queryParams['only_new'] === '1';
        
        if ($onlyNew) {
            $this->linkManager->clearNewLinks();
        } else {
            $this->linkManager->clearLinks();
        }
        
        // Redirect back to links page
        return $response->withHeader('Location', '/links')->withStatus(302);
    }
    
    /**
     * Export links
     */
    public function export(Request $request, Response $response, array $args): Response
    {
        $format = $args['format'] ?? 'csv';
        $queryParams = $request->getQueryParams();
        $category = $queryParams['category'] ?? null;
        
        // Generate a unique filename
        $timestamp = date('YmdHis');
        $filename = "links_export_{$timestamp}.{$format}";
        $filePath = __DIR__ . "/../../exports/{$filename}";
        
        // Create exports directory if it doesn't exist
        if (!file_exists(__DIR__ . "/../../exports")) {
            mkdir(__DIR__ . "/../../exports", 0755, true);
        }
        
        // Export the links
        if ($format === 'excel' || $format === 'xlsx') {
            // For Excel format, we'll use CSV as a fallback since we didn't include an Excel library
            $exportedFile = $this->linkManager->exportLinksToExcel(str_replace(".{$format}", '.csv', $filePath), $category);
            $filename = str_replace(".{$format}", '.csv', $filename);
        } else {
            // Default to CSV
            $exportedFile = $this->linkManager->exportLinksToExcel($filePath, $category);
        }
        
        if ($exportedFile) {
            // Set headers for download
            $response = $response->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Expires', '0')
                ->withHeader('Cache-Control', 'must-revalidate')
                ->withHeader('Pragma', 'public')
                ->withHeader('Content-Length', filesize($exportedFile));
                
            // Stream the file
            $file = fopen($exportedFile, 'rb');
            while (!feof($file)) {
                $response->getBody()->write(fread($file, 4096));
            }
            fclose($file);
            
            return $response;
        }
        
        // If export failed, redirect to links page with an error message
        return $response->withHeader('Location', '/links?error=export_failed')->withStatus(302);
    }
    
    /**
     * API endpoint to get links
     */
    public function apiLinks(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $category = $queryParams['category'] ?? null;
        $showAll = isset($queryParams['all']) && $queryParams['all'] === '1';
        
        $links = [];
        
        if ($showAll) {
            if ($category) {
                $links = $this->linkManager->getLinksByCategory($category);
            } else {
                $links = $this->linkManager->getAllLinks();
            }
        } else {
            if ($category) {
                $newLinks = $this->linkManager->getNewLinks();
                $categoryLinks = $this->linkManager->getLinksByCategory($category);
                $links = array_intersect($newLinks, $categoryLinks);
            } else {
                $links = $this->linkManager->getNewLinks();
            }
        }
        
        // Convert to JSON
        $data = [
            'success' => true,
            'count' => count($links),
            'links' => $links
        ];
        
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}