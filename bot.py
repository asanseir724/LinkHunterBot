import os
import re
import time
import requests
from logger import get_logger

# Get application logger
logger = get_logger(__name__)

class TelegramBot:
    """Simple Telegram Bot implementation using direct API calls"""
    
    def __init__(self, token):
        self.token = token
        self.api_base_url = f"https://api.telegram.org/bot{token}/"
    
    def make_request(self, method, params=None):
        """Make a request to the Telegram API"""
        url = self.api_base_url + method
        max_retries = 3
        retry_delay = 1  # seconds
        
        for attempt in range(max_retries):
            try:
                response = requests.post(url, data=params or {}, timeout=30)  # Add timeout
                response.raise_for_status()  # Raise exception for HTTP errors
                return response.json()
            except requests.exceptions.RequestException as e:
                logger.warning(f"Request failed (attempt {attempt+1}/{max_retries}): {e}")
                if attempt < max_retries - 1:
                    time.sleep(retry_delay)
                    retry_delay *= 2  # Exponential backoff
                else:
                    logger.error(f"Max retries reached for {method}")
                    return {"ok": False, "description": f"Request failed after {max_retries} attempts: {str(e)}"}
        
        # If we get here, something unexpected happened
        return {"ok": False, "description": "Unknown error occurred"}
    
    def get_updates(self, offset=None, timeout=30):
        """Get updates from Telegram API"""
        params = {'timeout': timeout}
        if offset:
            params['offset'] = offset
        return self.make_request('getUpdates', params)
    
    def get_chat(self, chat_id):
        """Get information about a chat"""
        params = {'chat_id': chat_id}
        return self.make_request('getChat', params)
    
    def send_message(self, chat_id, text):
        """Send a message to a chat"""
        params = {'chat_id': chat_id, 'text': text}
        return self.make_request('sendMessage', params)


def setup_bot(link_manager):
    """Set up and configure the Telegram bot"""
    
    # Get token from environment variable
    token = os.environ.get("TELEGRAM_BOT_TOKEN")
    if not token:
        logger.error("TELEGRAM_BOT_TOKEN environment variable not set")
        return None
    
    try:
        # Create a bot instance
        bot = TelegramBot(token)
        
        # Test the bot connection
        me = bot.make_request('getMe')
        if not me.get('ok'):
            logger.error(f"Failed to initialize bot: {me.get('description')}")
            return None
            
        logger.info(f"Bot initialized: @{me['result']['username']}")
        return bot
    except Exception as e:
        logger.error(f"Failed to initialize bot: {str(e)}")
        import traceback
        logger.error(f"Traceback: {traceback.format_exc()}")
        return None


def check_channels_for_links(bot, link_manager, max_channels=20):
    """
    Check monitored channels for new links
    
    Args:
        bot: The Telegram bot instance
        link_manager: The LinkManager instance
        max_channels: Maximum number of channels to check in one run (default: 20)
    
    Returns:
        int: Total number of new links found
    """
    if not bot:
        logger.error("Bot not initialized")
        return 0
        
    channels = link_manager.get_channels()
    total_new_links = 0
    channel_new_links = {}  # Track new links per channel
    
    if not channels:
        logger.info("No channels to check")
        return 0
    
    # Rate limiting - don't try to check too many channels at once
    # If there are too many channels, we'll check only a subset
    total_channel_count = len(channels)
    if total_channel_count > max_channels:
        logger.warning(f"Too many channels ({total_channel_count}), checking only {max_channels}")
        # Get the first max_channels channels
        channels_to_check = channels[:max_channels]
    else:
        channels_to_check = channels
    
    logger.info(f"Starting check for {len(channels_to_check)} channels (out of {total_channel_count})")
    
    for idx, channel in enumerate(channels_to_check):
        try:
            logger.info(f"Checking channel {idx+1}/{len(channels_to_check)}: {channel}")
            channel_new_links[channel] = 0
            
            # We'll try to get the latest messages from the channel
            # This requires the bot to be a member of the channel
            chat_id = f"@{channel}"
            
            try:
                # Get channel information to verify bot access
                chat_info = bot.get_chat(chat_id)
                if not chat_info.get('ok'):
                    logger.error(f"Error accessing channel {chat_id}: {chat_info.get('description')}")
                    continue
                    
                chat = chat_info['result']
                logger.info(f"Connected to channel: {chat.get('title', 'Unknown')} ({chat_id})")
                
                # Add a small delay between API calls to avoid rate limiting
                time.sleep(0.5)
                
                # Try to get the recent messages from this channel
                try:
                    # In a real-world scenario, we would use getHistory API
                    # For now, we'll try scraping first as it's more reliable
                    found_links = []
                    
                    # Try to fetch the channel's messages directly from URL (public channel messages)
                    try:
                        import requests
                        from bs4 import BeautifulSoup
                        
                        # Try to scrape public channel webpage for t.me links
                        channel_url = f"https://t.me/s/{channel}"
                        response = requests.get(channel_url, timeout=10)
                        
                        if response.status_code == 200:
                            soup = BeautifulSoup(response.text, 'html.parser')
                            message_texts = soup.select('.tgme_widget_message_text')
                            
                            for message in message_texts[:10]:  # Get the first 10 messages
                                message_text = message.get_text()
                                t_me_links = re.findall(r'https?://t\.me/[^\s]+', message_text)
                                if t_me_links:
                                    found_links.extend(t_me_links)
                            
                            logger.info(f"Found {len(found_links)} links by scraping channel webpage")
                        else:
                            logger.debug(f"Failed to scrape channel webpage, status: {response.status_code}")
                    except Exception as e:
                        logger.warning(f"Error scraping channel webpage: {str(e)}")
                        # Don't use fallback links in production
                    
                    # Only try API method if web scraping didn't work
                    if not found_links:
                        # Try to use Telegram API methods
                        try:
                            # First try getHistory if available
                            channel_id = chat.get('id')
                            history = bot.make_request('getHistory', {
                                'chat_id': channel_id,
                                'limit': 10
                            })
                            
                            if history.get('ok'):
                                messages = history.get('result', [])
                                logger.info(f"Got {len(messages)} messages from channel history")
                                
                                # Process messages for links
                                for message in messages:
                                    if 'text' in message:
                                        text = message['text']
                                        # Extract t.me links
                                        t_me_links = re.findall(r'https?://t\.me/[^\s]+', text)
                                        if t_me_links:
                                            found_links.extend(t_me_links)
                            else:
                                logger.debug("getHistory not supported or not authorized")
                                
                                # If getHistory didn't work, fall back to getUpdates
                                updates_response = bot.make_request('getUpdates', {
                                    'offset': -10,  # Get last 10 messages
                                    'limit': 10
                                })
                                
                                if updates_response.get('ok'):
                                    updates = updates_response.get('result', [])
                                    logger.debug(f"Retrieved {len(updates)} updates")
                                    
                                    # Process updates for links
                                    for update in updates:
                                        if 'message' in update and 'text' in update['message']:
                                            text = update['message']['text']
                                            t_me_links = re.findall(r'https?://t\.me/[^\s]+', text)
                                            if t_me_links:
                                                found_links.extend(t_me_links)
                        except Exception as e:
                            logger.warning(f"Error using Telegram API: {str(e)}")
                    
                    # Add the links to storage with channel info for categorization
                    for link in found_links:
                        logger.debug(f"Processing link: {link}")
                        if link_manager.add_link(link, channel=channel):
                            logger.info(f"Added new link: {link} from channel {channel}")
                            total_new_links += 1
                            channel_new_links[channel] += 1
                        else:
                            logger.debug(f"Link already exists: {link}")
                            
                except Exception as e:
                    logger.error(f"Error getting messages: {str(e)}")
                    import traceback
                    logger.error(f"Traceback: {traceback.format_exc()}")
                
            except Exception as e:
                logger.error(f"Error accessing channel {chat_id}: {str(e)}")
                continue  # Skip to next channel on error
            
            logger.info(f"Found {channel_new_links[channel]} new links in {channel}")
            
            # Add a delay between channels to avoid rate limiting
            if idx < len(channels_to_check) - 1:  # Don't delay after the last channel
                time.sleep(1)
                
        except Exception as e:
            logger.error(f"Error checking channel {channel}: {str(e)}")
            continue  # Skip to next channel on error
    
    # Update last check time
    link_manager.update_last_check_time()
    
    # Log summary
    logger.info(f"Total new links found: {total_new_links}")
    for channel, count in channel_new_links.items():
        if count > 0:  # Only log channels with new links
            logger.info(f"Channel {channel}: {count} new links")
    
    return total_new_links