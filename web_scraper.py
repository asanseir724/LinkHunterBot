import trafilatura
import requests
from bs4 import BeautifulSoup
import logging

# Set up logger
logger = logging.getLogger(__name__)

def get_website_text_content(url: str) -> str:
    """
    This function takes a url and returns the main text content of the website.
    The text content is extracted using trafilatura and easier to understand.
    The results is not directly readable, better to be summarized by LLM before consume
    by the user.

    Some common website to crawl information from:
    MLB scores: https://www.mlb.com/scores/YYYY-MM-DD
    """
    try:
        # Send a request to the website
        downloaded = trafilatura.fetch_url(url)
        if not downloaded:
            logger.warning(f"Failed to download content from {url}")
            return ""
            
        text = trafilatura.extract(downloaded)
        if not text:
            logger.warning(f"Failed to extract text content from {url}")
            return ""
            
        return text
    except Exception as e:
        logger.error(f"Error extracting content from {url}: {str(e)}")
        return ""

def get_links_from_webpage(url: str) -> list:
    """
    Extract all links from a webpage.
    
    Args:
        url: The URL of the webpage to scrape
        
    Returns:
        A list of links found on the page
    """
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        
        soup = BeautifulSoup(response.text, 'html.parser')
        links = []
        
        # Find all anchor tags
        for a_tag in soup.find_all('a', href=True):
            link = a_tag['href']
            # Make relative URLs absolute
            if link.startswith('/'):
                from urllib.parse import urlparse
                parsed_url = urlparse(url)
                base_url = f"{parsed_url.scheme}://{parsed_url.netloc}"
                link = base_url + link
            links.append(link)
        
        return links
    except Exception as e:
        logger.error(f"Error extracting links from {url}: {str(e)}")
        return []

def extract_telegram_links(content: str) -> list:
    """
    Extract Telegram links from text content.
    
    Args:
        content: The text content to extract links from
        
    Returns:
        A list of Telegram links found in the content
    """
    try:
        # Regular HTML parsing with BeautifulSoup
        soup = BeautifulSoup(content, 'html.parser')
        links = []
        
        # Find all anchor tags
        for a_tag in soup.find_all('a', href=True):
            href = a_tag['href']
            # Check if it's a Telegram link
            if 't.me' in href or 'telegram.me' in href:
                links.append(href)
                
        # If no links found with BeautifulSoup, try with simple string search
        if not links:
            import re
            # Find t.me links
            t_me_links = re.findall(r'https?://(?:t|telegram)\.me/[^\s<>"\']+', content)
            links.extend(t_me_links)
            
            # Find joinchat links
            joinchat_links = re.findall(r'https?://(?:t|telegram)\.me/joinchat/[^\s<>"\']+', content)
            links.extend(joinchat_links)
            
            # Find + links (private group links)
            plus_links = re.findall(r'https?://(?:t|telegram)\.me/\+[^\s<>"\']+', content)
            links.extend(plus_links)
            
        return list(set(links))  # Remove duplicates
    except Exception as e:
        logger.error(f"Error extracting Telegram links: {str(e)}")
        return []