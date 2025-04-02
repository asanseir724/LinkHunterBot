"""
Web crawler module for extracting Telegram links from websites with auto-scrolling capability.
This module uses Selenium to simulate browser behavior including scrolling for infinite scroll pages.
"""

import time
import re
import logging
from typing import List, Set, Dict, Optional
from urllib.parse import urlparse

from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, WebDriverException
from webdriver_manager.chrome import ChromeDriverManager

from logger import get_logger

# Setup logger
logger = get_logger("web_crawler")

# Regular expression for finding Telegram links
TELEGRAM_LINK_PATTERN = r'(?:https?://)?(?:www\.)?(?:t(?:elegram)?\.me|telegram\.org)/(?:joinchat/|\+)([a-zA-Z0-9_-]+)'


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
                chrome_options.add_argument("--headless")
            
            # Add required arguments for running in container environments
            chrome_options.add_argument("--no-sandbox")
            chrome_options.add_argument("--disable-dev-shm-usage")
            chrome_options.add_argument("--disable-gpu")
            chrome_options.add_argument("--window-size=1920,1080")
            
            # Disable images for faster loading
            chrome_prefs = {"profile.managed_default_content_settings.images": 2}
            chrome_options.add_experimental_option("prefs", chrome_prefs)
            
            # Initialize the Chrome driver
            service = Service(ChromeDriverManager().install())
            self.driver = webdriver.Chrome(service=service, options=chrome_options)
            
            logger.info("Chrome WebDriver initialized successfully")
        except Exception as e:
            logger.error(f"Failed to initialize Chrome WebDriver: {str(e)}")
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
        
        # Find t.me/joinchat and t.me/+ links
        matches = re.findall(r'https?://(?:www\.)?t(?:elegram)?\.me/(?:joinchat/|\+)([a-zA-Z0-9_-]+)', content)
        for match in matches:
            links.append(f"https://t.me/joinchat/{match}")
        
        # Find t.me/username links
        username_matches = re.findall(r'https?://(?:www\.)?t(?:elegram)?\.me/([a-zA-Z][a-zA-Z0-9_]{3,})', content)
        for match in username_matches:
            # Skip common non-channel usernames
            if match.lower() not in ['joinchat', 'share', 'home', 'login', 'download', 'features']:
                links.append(f"https://t.me/{match}")
        
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
    
    try:
        crawler = WebCrawler(headless=True)
        
        for url in urls:
            try:
                logger.info(f"Extracting links from {url}")
                links = crawler.extract_telegram_links(url, scroll_count=scroll_count)
                results[url] = list(links)
                logger.info(f"Found {len(links)} unique links from {url}")
            except Exception as e:
                logger.error(f"Error extracting links from {url}: {str(e)}")
                results[url] = []
        
        crawler.close()
    except Exception as e:
        logger.error(f"Error in extract_links_from_websites: {str(e)}")
    
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