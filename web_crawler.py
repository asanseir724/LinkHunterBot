"""
Web crawler module for extracting Telegram links from websites with auto-scrolling capability.
This module offers two methods:
1. Selenium for dynamic websites with scrolling (if available)
2. Requests+BeautifulSoup as a fallback for static content
"""

import time
import re
import logging
import requests
from bs4 import BeautifulSoup
import traceback
from typing import List, Set, Dict, Optional
from urllib.parse import urlparse

# Selenium imports - may fail in some environments
try:
    from selenium import webdriver
    from selenium.webdriver.chrome.service import Service
    from selenium.webdriver.chrome.options import Options
    from selenium.webdriver.common.by import By
    from selenium.webdriver.support.ui import WebDriverWait
    from selenium.webdriver.support import expected_conditions as EC
    from selenium.common.exceptions import TimeoutException, WebDriverException
    from webdriver_manager.chrome import ChromeDriverManager
    SELENIUM_AVAILABLE = True
except ImportError:
    SELENIUM_AVAILABLE = False
    print("Selenium not available, will use requests/BeautifulSoup instead")

from logger import get_logger

# Setup logger
logger = get_logger("web_crawler")

# Regular expressions for finding Telegram links
# 1. Pattern for invite links (t.me/joinchat or t.me/+)
TELEGRAM_INVITE_PATTERN = r'(?:https?://)?(?:www\.)?(?:t(?:elegram)?\.me|telegram\.org)/(?:joinchat/|\+)([a-zA-Z0-9_-]+)'
# 2. Pattern for public group/channel links with @ or t.me format
TELEGRAM_PUBLIC_PATTERN = r'(?:@([a-zA-Z0-9_]{5,})|(?:https?://)?(?:www\.)?(?:t(?:elegram)?\.me|telegram\.org)/([a-zA-Z0-9_]{5,}))'
# 3. Pattern for @ username format specifically
AT_USERNAME_PATTERN = r'@([a-zA-Z][a-zA-Z0-9_]{3,})'


class WebCrawler:
    """Web crawler for extracting Telegram links from websites with auto-scrolling capability."""
    
    def __init__(self, headless: bool = True):
        """
        Initialize the WebCrawler.
        
        Args:
            headless (bool): Whether to run the browser in headless mode
        """
        self.headless = headless
        self.driver = None
        self.setup_driver()
    
    def setup_driver(self):
        """Setup Selenium WebDriver with Chrome."""
        try:
            chrome_options = Options()
            if self.headless:
                chrome_options.add_argument("--headless=new")
            
            # Add required arguments for running in container environments
            chrome_options.add_argument("--no-sandbox")
            chrome_options.add_argument("--disable-dev-shm-usage")
            chrome_options.add_argument("--disable-gpu")
            chrome_options.add_argument("--window-size=1920,1080")
            
            # Disable images for faster loading
            chrome_prefs = {"profile.managed_default_content_settings.images": 2}
            chrome_options.add_experimental_option("prefs", chrome_prefs)
            
            # For Replit environment
            chrome_options.binary_location = "/nix/store/xxx-chromium/bin/chromium"  # Will be ignored if not exists

            # Try with different approaches for better compatibility
            try:
                # First approach - using ChromeDriverManager
                service = Service(ChromeDriverManager().install())
                self.driver = webdriver.Chrome(service=service, options=chrome_options)
            except Exception as inner_e:
                logger.warning(f"First driver approach failed: {str(inner_e)}, trying alternative...")
                
                # Second approach - using direct Chrome
                self.driver = webdriver.Chrome(options=chrome_options)
            
            logger.info("Chrome WebDriver initialized successfully")
        except Exception as e:
            logger.error(f"Failed to initialize Chrome WebDriver: {str(e)}")
            # Try a fallback method for Replit environment
            try:
                logger.info("Trying fallback method for Chrome initialization...")
                from selenium.webdriver.chrome.service import Service as ChromeService
                chrome_options = Options()
                chrome_options.add_argument("--headless=new")
                chrome_options.add_argument("--no-sandbox")
                chrome_options.add_argument("--disable-dev-shm-usage")
                self.driver = webdriver.Chrome(options=chrome_options)
                logger.info("Fallback Chrome initialization successful")
            except Exception as fallback_e:
                logger.error(f"Fallback Chrome initialization also failed: {str(fallback_e)}")
                raise
    
    def close(self):
        """Close the WebDriver."""
        if self.driver:
            self.driver.quit()
            logger.info("WebDriver closed")
    
    def extract_telegram_links(self, url: str, scroll_count: int = 5, scroll_pause: float = 2.0) -> Set[str]:
        """
        Extract Telegram links from a webpage with auto-scrolling.
        
        Args:
            url (str): The URL of the webpage to extract links from
            scroll_count (int): Number of times to scroll the page
            scroll_pause (float): Pause duration between scrolls in seconds
        
        Returns:
            Set[str]: Set of unique Telegram links found on the page
        """
        if not self.driver:
            self.setup_driver()
        
        telegram_links = set()
        
        try:
            logger.info(f"Opening URL: {url}")
            self.driver.get(url)
            
            # Wait for the page to load
            WebDriverWait(self.driver, 10).until(
                EC.presence_of_element_located((By.TAG_NAME, "body"))
            )
            
            # Initial page content before scrolling
            page_content = self.driver.page_source
            new_links = self._find_telegram_links(page_content)
            telegram_links.update(new_links)
            logger.info(f"Found {len(new_links)} links before scrolling")
            
            # Scroll and collect more links
            scroll_script = "window.scrollTo(0, document.body.scrollHeight);"
            last_height = self.driver.execute_script("return document.body.scrollHeight")
            
            for i in range(scroll_count):
                logger.debug(f"Scrolling ({i+1}/{scroll_count})...")
                self.driver.execute_script(scroll_script)
                
                # Wait for page to load new content
                time.sleep(scroll_pause)
                
                # Calculate new scroll height and compare with last scroll height
                new_height = self.driver.execute_script("return document.body.scrollHeight")
                
                # Extract links from the updated page
                page_content = self.driver.page_source
                new_links = self._find_telegram_links(page_content)
                prev_count = len(telegram_links)
                telegram_links.update(new_links)
                
                logger.debug(f"Found {len(telegram_links) - prev_count} new links after scroll {i+1}")
                
                # If heights are the same, we've reached the end of the page
                if new_height == last_height:
                    logger.info("Reached the end of the page, stopping scrolling")
                    break
                
                last_height = new_height
            
            logger.info(f"Total unique Telegram links found: {len(telegram_links)}")
            return telegram_links
            
        except TimeoutException:
            logger.error(f"Timeout waiting for page to load: {url}")
        except WebDriverException as e:
            logger.error(f"WebDriver error while processing {url}: {str(e)}")
        except Exception as e:
            logger.error(f"Error extracting links from {url}: {str(e)}")
        
        return telegram_links
    
    def _find_telegram_links(self, content: str) -> Set[str]:
        """
        Find Telegram links in the content using regex.
        
        Args:
            content (str): HTML content to search
            
        Returns:
            Set[str]: Set of found Telegram links
        """
        # Find all matches in the content
        links = []
        
        # Find t.me/joinchat and t.me/+ links (private groups/channels)
        invite_matches = re.findall(r'https?://(?:www\.)?t(?:elegram)?\.me/(?:joinchat/|\+)([a-zA-Z0-9_-]+)', content)
        for match in invite_matches:
            links.append(f"https://t.me/joinchat/{match}")
        
        # Find t.me/username links (public channels/groups)
        username_matches = re.findall(r'https?://(?:www\.)?t(?:elegram)?\.me/([a-zA-Z][a-zA-Z0-9_]{3,})', content)
        for match in username_matches:
            # Skip common non-channel usernames
            if match.lower() not in ['joinchat', 'share', 'home', 'login', 'download', 'features', 
                                     'contact', 'privacy', 'faq', 'blog', 'terms', 'apps', 'premium']:
                links.append(f"https://t.me/{match}")
        
        # Find @username mentions (public channels/groups)
        # This is especially important for sites like combot.org that list Telegram groups with @ format
        at_username_matches = re.findall(r'@([a-zA-Z][a-zA-Z0-9_]{3,})', content)
        for match in at_username_matches:
            # Skip if it looks like an email address or common words
            if '.' not in match and match.lower() not in ['gmail', 'yahoo', 'hotmail', 'outlook', 'mail', 'email']:
                # For combot.org and similar sites, return the @username format
                # which will be normalized in link_manager.add_website_link
                links.append(f"@{match}")
        
        # Remove duplicates and return
        return set(links)

    def batch_process_urls(self, urls: List[str], scroll_count: int = 5) -> Dict[str, Set[str]]:
        """
        Process a batch of URLs and extract Telegram links.
        
        Args:
            urls (List[str]): List of URLs to process
            scroll_count (int): Number of times to scroll each page
            
        Returns:
            Dict[str, Set[str]]: Dictionary mapping from URL to the set of Telegram links found
        """
        results = {}
        
        for url in urls:
            try:
                logger.info(f"Processing URL: {url}")
                links = self.extract_telegram_links(url, scroll_count)
                results[url] = links
                
                # Short pause between requests to avoid overloading servers
                time.sleep(1.5)
                
            except Exception as e:
                logger.error(f"Error processing URL {url}: {str(e)}")
                results[url] = set()
        
        return results


def extract_links_with_requests(url: str) -> Set[str]:
    """
    Extract Telegram links using simple requests and BeautifulSoup instead of Selenium.
    This is a fallback method when Selenium is not available or fails.
    
    Args:
        url (str): URL to extract links from
        
    Returns:
        Set[str]: Set of unique Telegram links found
    """
    telegram_links = set()
    
    try:
        logger.info(f"Using requests to extract links from: {url}")
        
        # Use a modern User-Agent to avoid being blocked
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
        }
        
        # Make the request
        response = requests.get(url, headers=headers, timeout=20)
        if response.status_code != 200:
            logger.warning(f"Got status code {response.status_code} from {url}")
            return telegram_links
            
        # Parse the HTML content
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # Get the text content to find @username mentions
        text_content = soup.get_text()
        links_from_text = _find_telegram_links_static(text_content)
        telegram_links.update(links_from_text)
        
        # Also extract all href attributes from links
        for a_tag in soup.find_all('a', href=True):
            href = a_tag['href']
            if 't.me' in href or 'telegram.me' in href:
                telegram_links.add(href)
            
        # Extract from the full HTML to catch any links in attributes, etc.
        links_from_html = _find_telegram_links_static(response.text)
        telegram_links.update(links_from_html)
        
        logger.info(f"Found {len(telegram_links)} links using requests method from {url}")
    except Exception as e:
        logger.error(f"Error extracting links with requests from {url}: {str(e)}")
        logger.debug(f"Traceback: {traceback.format_exc()}")
    
    return telegram_links


def _find_telegram_links_static(content: str) -> Set[str]:
    """
    Static version of find_telegram_links that can be used without a WebCrawler instance.
    
    Args:
        content (str): HTML or text content to search
        
    Returns:
        Set[str]: Set of found Telegram links
    """
    links = []
    
    # Find t.me/joinchat and t.me/+ links (private groups/channels)
    invite_matches = re.findall(r'https?://(?:www\.)?t(?:elegram)?\.me/(?:joinchat/|\+)([a-zA-Z0-9_-]+)', content)
    for match in invite_matches:
        links.append(f"https://t.me/joinchat/{match}")
    
    # Find t.me/username links (public channels/groups)
    username_matches = re.findall(r'https?://(?:www\.)?t(?:elegram)?\.me/([a-zA-Z][a-zA-Z0-9_]{3,})', content)
    for match in username_matches:
        # Skip common non-channel usernames
        if match.lower() not in ['joinchat', 'share', 'home', 'login', 'download', 'features', 
                               'contact', 'privacy', 'faq', 'blog', 'terms', 'apps', 'premium']:
            links.append(f"https://t.me/{match}")
    
    # Find @username mentions (public channels/groups)
    at_username_matches = re.findall(r'@([a-zA-Z][a-zA-Z0-9_]{3,})', content)
    for match in at_username_matches:
        # Skip if it looks like an email address or common words
        if '.' not in match and match.lower() not in ['gmail', 'yahoo', 'hotmail', 'outlook', 'mail', 'email']:
            links.append(f"@{match}")
    
    # Remove duplicates and return
    return set(links)


def extract_links_from_websites(urls: List[str], scroll_count: int = 5) -> Dict[str, List[str]]:
    """
    Extract Telegram links from a list of websites.
    
    Args:
        urls (List[str]): List of URLs to extract links from
        scroll_count (int): Number of times to scroll each page
        
    Returns:
        Dict[str, List[str]]: Dictionary mapping from URL to the list of Telegram links found
    """
    results = {}
    
    # First try using Selenium if available
    if SELENIUM_AVAILABLE:
        try:
            logger.info("Attempting to extract links using Selenium...")
            crawler = WebCrawler(headless=True)
            
            for url in urls:
                try:
                    logger.info(f"Extracting links from {url} with Selenium")
                    links = crawler.extract_telegram_links(url, scroll_count=scroll_count)
                    results[url] = list(links)
                    logger.info(f"Found {len(links)} unique links from {url} with Selenium")
                except Exception as e:
                    logger.error(f"Selenium error extracting from {url}: {str(e)}")
                    # Fallback to requests-based extraction for this URL
                    logger.info(f"Falling back to requests-based extraction for {url}")
                    links = extract_links_with_requests(url)
                    results[url] = list(links)
            
            crawler.close()
            return results
            
        except Exception as e:
            logger.error(f"Error in Selenium extraction, falling back to requests: {str(e)}")
    
    # If Selenium is not available or failed, use requests/BeautifulSoup
    logger.info("Using requests/BeautifulSoup for extraction (fallback method)...")
    
    for url in urls:
        try:
            links = extract_links_with_requests(url)
            results[url] = list(links)
            logger.info(f"Found {len(links)} unique links from {url} with requests")
        except Exception as e:
            logger.error(f"Error extracting links from {url}: {str(e)}")
            results[url] = []
    
    return results


# Test function
if __name__ == "__main__":
    test_urls = [
        "https://example.com/telegram-groups",  # Replace with a real website
    ]
    
    results = extract_links_from_websites(test_urls, scroll_count=3)
    
    for url, links in results.items():
        print(f"\nLinks from {url}:")
        for link in links:
            print(f"  - {link}")