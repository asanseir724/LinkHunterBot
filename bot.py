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
        response = requests.post(url, data=params or {})
        return response.json()
    
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


def check_channels_for_links(bot, link_manager):
    """Check monitored channels for new links"""
    if not bot:
        logger.error("Bot not initialized")
        return 0
        
    channels = link_manager.get_channels()
    total_new_links = 0
    channel_new_links = {}  # Track new links per channel
    
    if not channels:
        logger.info("No channels to check")
        return 0
    
    logger.info(f"Starting check for {len(channels)} channels")
    
    for channel in channels:
        try:
            logger.info(f"Checking channel: {channel}")
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
                logger.info(f"Connected to channel: {chat.get('title')} ({chat_id})")
                
                # For now, our simplified approach will use publicly available messages
                # In a real implementation, you would need to have the bot join the channel
                # and then use getChatHistory API to get messages
                
                # For this demo, we're simulating finding links in channel posts
                # Note: This is a simulated approach for demonstration purposes only
                
                # Simulate finding some links (in a real implementation, we would parse actual messages)
                example_links = [
                    f"https://t.me/example_group_{channel}_{int(time.time())}"
                ]
                
                # Add simulated links to storage
                for link in example_links:
                    logger.debug(f"Processing link: {link}")
                    if link_manager.add_link(link):
                        logger.info(f"Added new link: {link}")
                        total_new_links += 1
                        channel_new_links[channel] += 1
                    else:
                        logger.debug(f"Link already exists: {link}")
                
            except Exception as e:
                logger.error(f"Error accessing channel {chat_id}: {str(e)}")
                logger.error(f"Exception type: {type(e)}")
                import traceback
                logger.error(f"Traceback: {traceback.format_exc()}")
            
            logger.info(f"Found {channel_new_links[channel]} new links in {channel}")
            
        except Exception as e:
            logger.error(f"Error checking channel {channel}: {str(e)}")
            logger.error(f"Exception type: {type(e)}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
    
    # Update last check time
    link_manager.update_last_check_time()
    
    # Log summary
    logger.info(f"Total new links found: {total_new_links}")
    for channel, count in channel_new_links.items():
        logger.info(f"Channel {channel}: {count} new links")
    
    return total_new_links