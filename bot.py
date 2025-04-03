import os
import re
import time
import requests
import threading
from datetime import datetime
from logger import get_logger
from avalai_api import avalai_client
from perplexity_api import perplexity_client

# Get application logger
logger = get_logger(__name__)

class TelegramBot:
    """Simple Telegram Bot implementation using direct API calls"""
    
    def __init__(self, token):
        self.token = token
        self.api_base_url = f"https://api.telegram.org/bot{token}/"
        self.last_update_id = 0
        self.private_message_handlers = []
    
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
        
    def register_private_message_handler(self, handler_function):
        """Register a handler for private messages
        
        Args:
            handler_function: Function that takes a message object as parameter
        """
        self.private_message_handlers.append(handler_function)
        logger.critical(f"[BOT_DEBUG] Registered new message handler, total: {len(self.private_message_handlers)}")
        return True
        
    def _handle_private_message(self, message):
        """Internal handler for private messages
        
        Calls all registered handlers with the message
        """
        try:
            # Get message details
            message_text = message.get('text', '')
            chat_id = message['chat']['id']
            user_id = message['from']['id']
            
            logger.critical(f"[BOT_DIRECT_DEBUG] Received private message from user {user_id}: {message_text[:50]}...")
            
            # Extract user information
            username = message['from'].get('username', '')
            first_name = message['from'].get('first_name', '')
            last_name = message['from'].get('last_name', '')
            display_name = username or f"{first_name} {last_name}".strip() or f"کاربر {user_id}"
            
            # Create conversation ID
            conversation_id = f"direct_{user_id}"
            
            # Create metadata for logging
            message_metadata = {
                "user_id": str(user_id),
                "username": username,
                "first_name": first_name,
                "last_name": last_name,
                "display_name": display_name,
                "chat_id": str(chat_id),
                "received_at": datetime.now().isoformat(),
                "conversation_id": conversation_id,
                "via": "direct_api"
            }
            
            logger.critical(f"[BOT_DIRECT_DEBUG] Message metadata: {message_metadata}")
            
            # First check if Perplexity API is enabled - it takes priority if both are enabled
            if perplexity_client.is_enabled():
                logger.critical(f"[BOT_DIRECT_DEBUG] Generating Perplexity AI response for {display_name}")
                
                # Request AI response from Perplexity
                response_data = perplexity_client.generate_response(
                    user_message=message_text,
                    user_id=str(user_id),
                    username=display_name,
                    conversation_id=conversation_id,
                    metadata=message_metadata
                )
                
                if response_data["success"] and response_data["response"]:
                    # Send AI response
                    ai_response = response_data["response"]
                    logger.critical(f"[BOT_DIRECT_DEBUG] Sending Perplexity AI response: {ai_response[:50]}...")
                    self.send_message(chat_id, ai_response)
                else:
                    error = response_data.get("error", "دریافت پاسخ با خطا مواجه شد")
                    logger.critical(f"[BOT_DIRECT_DEBUG] Failed to get Perplexity AI response: {error}")
                    
                    # Log error in Perplexity chat history
                    perplexity_client._log_chat(
                        user_message=message_text,
                        ai_response=f"[خطا در دریافت پاسخ Perplexity: {error}]",
                        user_id=str(user_id),
                        username=display_name,
                        metadata=message_metadata
                    )
                
                # Always log to Avalai history for display in admin panel (if Avalai is enabled)
                if avalai_client.is_enabled():
                    avalai_client._log_chat(
                        user_message=message_text,
                        ai_response=response_data.get("response", "[پاسخ توسط Perplexity ارسال شد]"),
                        user_id=str(user_id),
                        username=display_name,
                        metadata=message_metadata
                    )
                
                return
            
            # If Perplexity is not enabled, try Avalai
            if avalai_client.is_enabled():
                # Always respond in debug mode
                logger.critical(f"[BOT_DIRECT_DEBUG] Generating Avalai response for {display_name}")
                
                # Request AI response from Avalai
                response_data = avalai_client.generate_response(
                    user_message=message_text,
                    user_id=str(user_id),
                    username=display_name,
                    conversation_id=conversation_id,
                    metadata=message_metadata
                )
                
                if response_data["success"] and response_data["response"]:
                    # Send AI response
                    ai_response = response_data["response"]
                    logger.critical(f"[BOT_DIRECT_DEBUG] Sending Avalai response: {ai_response[:50]}...")
                    self.send_message(chat_id, ai_response)
                else:
                    error = response_data.get("error", "دریافت پاسخ با خطا مواجه شد")
                    logger.critical(f"[BOT_DIRECT_DEBUG] Failed to get Avalai response: {error}")
                    
                    # Log error in chat history
                    avalai_client._log_chat(
                        user_message=message_text,
                        ai_response=f"[خطا در دریافت پاسخ آوالای: {error}]",
                        user_id=str(user_id),
                        username=display_name,
                        metadata=message_metadata
                    )
                
                return
            
            # If no AI service is enabled, log without responding
            logger.critical("[BOT_DIRECT_DEBUG] No AI service is enabled, logging message but not responding")
            # Still log message in Avalai history without response (for admin panel)
            avalai_client._log_chat(
                user_message=message_text,
                ai_response="[پاسخی ارسال نشد - هیچ سرویس هوش مصنوعی فعال نیست]",
                user_id=str(user_id),
                username=display_name,
                metadata=message_metadata
            )
                
        except Exception as e:
            logger.critical(f"[BOT_DIRECT_DEBUG] Error handling private message: {str(e)}")
            import traceback
            logger.critical(f"[BOT_DIRECT_DEBUG] Traceback: {traceback.format_exc()}")
            
    def start_polling(self):
        """Start polling for updates in background thread"""
        logger.critical("[BOT_DEBUG] Starting background update polling")
        
        def update_worker():
            while True:
                try:
                    # Get updates with offset
                    updates_result = self.get_updates(offset=self.last_update_id + 1, timeout=30)
                    
                    if updates_result.get('ok'):
                        updates = updates_result.get('result', [])
                        
                        if updates:
                            logger.critical(f"[BOT_DEBUG] Received {len(updates)} updates")
                            
                            for update in updates:
                                # Update the offset
                                if update['update_id'] > self.last_update_id:
                                    self.last_update_id = update['update_id']
                                
                                # Debug log the entire update for inspection
                                try:
                                    import json
                                    logger.critical(f"[BOT_DEBUG] Received update: {json.dumps(update, ensure_ascii=False)[:1000]}")
                                except Exception as log_err:
                                    logger.critical(f"[BOT_DEBUG] Error logging update: {str(log_err)}")
                                
                                # Handle private messages
                                if 'message' in update and 'chat' in update['message'] and update['message']['chat']['type'] == 'private':
                                    logger.critical(f"[BOT_DEBUG] Received private message from user {update['message'].get('from', {}).get('id')}: {update['message'].get('text', '')[:100]}")
                                    
                                    # Handle this private message
                                    self._handle_private_message(update['message'])
                                    
                                    # Always save message to chat history to ensure it appears in the admin panel
                                    try:
                                        message = update['message']
                                        user_id = message.get('from', {}).get('id', 'unknown')
                                        username = message.get('from', {}).get('username', '')
                                        first_name = message.get('from', {}).get('first_name', '')
                                        last_name = message.get('from', {}).get('last_name', '')
                                        display_name = username or f"{first_name} {last_name}".strip() or f"کاربر {user_id}"
                                        message_text = message.get('text', '')
                                        
                                        # Create additional metadata for troubleshooting
                                        message_metadata = {
                                            "user_id": str(user_id),
                                            "username": username, 
                                            "display_name": display_name,
                                            "received_at": datetime.now().isoformat(),
                                            "via": "direct_bot_api",
                                            "bot_username": "@tourbotsbot"
                                        }
                                        
                                        # Try Perplexity first (if enabled), then fallback to Avalai
                                        if perplexity_client.is_enabled():
                                            logger.critical(f"[BOT_DEBUG] Saving message to Perplexity history: {message_text[:100]}")
                                            
                                            # Force save to chat history
                                            perplexity_client._log_chat(
                                                user_message=message_text,
                                                ai_response="[در حال پردازش پاسخ با Perplexity...]",
                                                user_id=str(user_id),
                                                username=display_name,
                                                metadata=message_metadata
                                            )
                                            
                                        # Always log to Avalai too for the admin panel
                                        if avalai_client.is_enabled():
                                            logger.critical(f"[BOT_DEBUG] Saving message to Avalai history: {message_text[:100]}")
                                            
                                            # Force save to chat history
                                            avalai_client._log_chat(
                                                user_message=message_text,
                                                ai_response="[در حال پردازش پاسخ...]",
                                                user_id=str(user_id),
                                                username=display_name,
                                                metadata=message_metadata
                                            )
                                    except Exception as e:
                                        logger.critical(f"[BOT_DEBUG] Error saving message to chat history: {str(e)}")
                    
                    # Sleep a bit to avoid hammering the API
                    time.sleep(1)
                    
                except Exception as e:
                    logger.critical(f"[BOT_DEBUG] Error in update worker: {str(e)}")
                    import traceback
                    logger.critical(f"[BOT_DEBUG] Traceback: {traceback.format_exc()}")
                    # Sleep a bit longer on error
                    time.sleep(5)
                    
        # Start the worker in a background thread
        thread = threading.Thread(target=update_worker, daemon=True)
        thread.start()
        logger.critical("[BOT_DEBUG] Background update thread started")


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


def check_channels_for_links(bot, link_manager, max_channels=100):
    """
    Check monitored channels for new links
    
    Args:
        bot: The Telegram bot instance
        link_manager: The LinkManager instance
        max_channels: Maximum number of channels to check in one run (default: 100)
    
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
                    
                    # Initialize variable for later use in link processing
                    all_message_texts = []
                    
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
                            
                            # Store message_texts for later use
                            all_message_texts = message_texts.copy()
                            
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
                    
                    # Add the links to storage with channel info and message content for categorization
                    for link in found_links:
                        logger.debug(f"Processing link: {link}")
                        
                        # Get the message text containing this link if available
                        link_message_text = None
                        if 'all_message_texts' in locals() and all_message_texts:
                            for message in all_message_texts[:10]:
                                message_text = message.get_text()
                                if link in message_text:
                                    link_message_text = message_text
                                    break
                        
                        # Use the message text for keyword detection when adding the link
                        if link_manager.add_link(link, channel=channel, message_text=link_message_text):
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
                
                # Check if this is a "chat not found" error
                error_str = str(e).lower()
                if "chat not found" in error_str or "bad request" in error_str or "404" in error_str:
                    logger.warning(f"Channel {channel} does not exist or bot cannot access it - consider removing it")
                    # You could add automatic channel removal here if desired
                    # link_manager.remove_channel(channel)
                
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