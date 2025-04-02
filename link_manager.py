import json
import os
import re
import time
from datetime import datetime
from urllib.parse import urlparse, parse_qs
from logger import get_logger

try:
    import pandas as pd
except ImportError:
    pd = None

# Get module logger
logger = get_logger(__name__)

# SMS Notification settings
SMS_NOTIFICATION_SETTINGS = {
    "enabled": False,
    "phone_number": None,
    "min_links": 5,
    "twilio_configured": False
}

class LinkManager:
    """Manages link extraction, storage, and monitoring of channels"""
    
    def __init__(self, data_file="links_data.json"):
        self.data_file = data_file
        self.channels = []  # List of channels to monitor
        self.links = []     # List of all links (history)
        self.new_links = [] # List of new links (current session)
        self.channel_categories = {}  # Dictionary mapping channel names to categories
        self.links_by_category = {}   # Dictionary mapping categories to links
        self.channel_link_counts = {} # Dictionary to track link count per channel
        self.auto_discover = True     # Auto-discover new link-sharing channels
        self.check_message_count = 10 # Number of recent messages to check per channel
        self.telegram_tokens = []     # List of Telegram bot tokens to use in rotation
        self.current_token_index = 0  # Current index for token rotation
        
        # Default categories
        self.default_categories = ["عمومی", "سرگرمی", "فیلم", "موسیقی", "علمی", "خبری", "ورزشی", "آموزشی", "لینکدونی"]
        
        # Keywords to identify link directory channels (linkdoni)
        self.linkdoni_keywords = [
            "لینکدونی", "لینک دونی", "لینک یاب", "لینکیاب", "linkdoni", "link directory", 
            "کانال یاب", "گروه یاب", "گپ یاب", "لینک گروه", "گروهکده", "گروه چت", 
            "دایرکتوری لینک", "لینک جدید", "لینک کده", "لینکدونیا", "linkdonee"
        ]
        
        # Category keywords mapping - for automatic categorization based on message text
        self.category_keywords = {
            "سرگرمی": [
                "تفریح", "سرگرمی", "فان", "جوک", "طنز", "بازی", "خنده", "موزیک", "شوخی", "گیم", "سرگرم",
                "fun", "game", "play", "joke", "meme", "vip", "فیلم", "چت", "دوستیابی", "خاص",
                "دورهمی", "chat", "باحال", "جذاب", "میتینگ", "رفیق", "مود", "شاد", "حال", "خوش", "کلیپ", "گپ"
            ],
            "فیلم": [
                "فیلم", "سریال", "سینما", "اکشن", "کمدی", "درام", "هالیوود", "بالیوود", "انیمیشن", "کارتون",
                "movie", "film", "cinema", "serial", "سینمایی", "ترسناک", "هیجانی", "دانلود فیلم", "مستند", 
                "کلیپ", "رایگان", "تماشا", "مووی", "video", "دانلود", "پخش", "استوری"
            ],
            "موسیقی": [
                "موسیقی", "آهنگ", "موزیک", "خواننده", "ترانه", "پاپ", "رپ", "سنتی", "راک", "music",
                "song", "rap", "pop", "artist", "کنسرت", "میکس", "دانلود", "جدید", "گوش", "mp3",
                "آلبوم", "ملودی", "زنگ", "پلی لیست", "صوتی", "خواننده", "انگلیسی", "ایرانی", "خارجی"
            ],
            "علمی": [
                "علم", "دانش", "فناوری", "تکنولوژی", "آموزش", "یادگیری", "پژوهش", "تحقیق", "مقاله", "کتاب",
                "science", "tech", "learn", "research", "study", "book", "paper", "physics", "chemistry",
                "تاریخ", "زیست", "شیمی", "فیزیک", "ریاضی", "نجوم", "پزشکی", "مهندسی", "دانشگاه", "دانشجو", "معلم"
            ],
            "خبری": [
                "خبر", "اخبار", "تازه", "رویداد", "حادثه", "سیاست", "اقتصاد", "اجتماعی", "جامعه", "جهان",
                "news", "report", "event", "politics", "economy", "social", "روزنامه", "خبرگزاری", "گزارش",
                "فوری", "تحلیل", "خبرنگار", "تیتر", "بحران", "بررسی", "جدیدترین", "اطلاعیه", "شبکه"
            ],
            "ورزشی": [
                "ورزش", "فوتبال", "والیبال", "بسکتبال", "تنیس", "ورزشکار", "قهرمان", "المپیک", "جام جهانی",
                "sport", "football", "soccer", "basketball", "volleyball", "tennis", "champion", "Olympic",
                "مسابقه", "فیفا", "استقلال", "پرسپولیس", "باشگاه", "بازی", "لیگ", "جام", "گل", "تیم", "باشگاه"
            ],
            "آموزشی": [
                "آموزش", "یادگیری", "درس", "مدرسه", "معلم", "استاد", "دانشگاه", "دانشجو", "دانش آموز", "تحصیل",
                "education", "learn", "study", "school", "teacher", "professor", "university", "student",
                "کلاس", "کنکور", "زبان", "تدریس", "کتاب", "جزوه", "نمونه سوال", "آزمون", "مشاوره", "رشته تحصیلی"
            ]
        }
        
        self.check_interval = 5  # Default check interval in minutes (changed to 5)
        self.last_check = None
        self.telegram_token = None  # Store the Telegram Bot Token
        
        # Load data from file if exists
        self.load_data()
    
    def load_data(self):
        """Load data from JSON file if it exists"""
        global SMS_NOTIFICATION_SETTINGS
        
        if os.path.exists(self.data_file):
            try:
                with open(self.data_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    self.channels = data.get('channels', [])
                    self.links = data.get('links', [])
                    self.new_links = data.get('new_links', [])
                    self.channel_categories = data.get('channel_categories', {})
                    self.links_by_category = data.get('links_by_category', {})
                    self.check_interval = data.get('check_interval', 5)
                    self.last_check = data.get('last_check')
                    self.telegram_token = data.get('telegram_token')
                    self.telegram_tokens = data.get('telegram_tokens', [])
                    self.current_token_index = data.get('current_token_index', 0)
                    self.channel_link_counts = data.get('channel_link_counts', {})
                    self.auto_discover = data.get('auto_discover', True)
                    self.check_message_count = data.get('check_message_count', 10)
                    
                    # Load category keywords from file if available
                    if 'category_keywords' in data:
                        self.category_keywords = data.get('category_keywords', self.category_keywords)
                    
                    # Load SMS notification settings if available
                    sms_notification_data = data.get('sms_notification', {})
                    if sms_notification_data:
                        SMS_NOTIFICATION_SETTINGS.update({
                            'enabled': sms_notification_data.get('enabled', False),
                            'phone_number': sms_notification_data.get('phone_number'),
                            'min_links': sms_notification_data.get('min_links', 5)
                        })
                        
                        # Check if Twilio credentials are configured
                        twilio_sid = os.environ.get("TWILIO_ACCOUNT_SID")
                        twilio_token = os.environ.get("TWILIO_AUTH_TOKEN")
                        twilio_phone = os.environ.get("TWILIO_PHONE_NUMBER")
                        
                        SMS_NOTIFICATION_SETTINGS['twilio_configured'] = all([
                            twilio_sid, twilio_token, twilio_phone
                        ])
                    
                    # If we have a token, set it in the environment
                    if self.telegram_token:
                        os.environ["TELEGRAM_BOT_TOKEN"] = self.telegram_token
                        logger.info("Loaded Telegram token from storage")
                        
                    # Make sure the main token is in the tokens list
                    if self.telegram_token and self.telegram_token not in self.telegram_tokens:
                        self.telegram_tokens.append(self.telegram_token)
                        logger.debug("Added main token to the token rotation list")
                        
                logger.info(f"Loaded data: {len(self.channels)} channels, {len(self.links)} links, {len(self.new_links)} new links")
            except Exception as e:
                logger.error(f"Error loading data: {e}")
    
    def save_data(self):
        """Save data to JSON file"""
        try:
            # Get global SMS notification settings
            global SMS_NOTIFICATION_SETTINGS
            
            data = {
                'channels': self.channels,
                'links': self.links,
                'new_links': self.new_links,
                'channel_categories': self.channel_categories,
                'links_by_category': self.links_by_category,
                'check_interval': self.check_interval,
                'last_check': self.last_check,
                'telegram_token': self.telegram_token,
                'telegram_tokens': getattr(self, 'telegram_tokens', []),
                'current_token_index': getattr(self, 'current_token_index', 0),
                'channel_link_counts': self.channel_link_counts,
                'auto_discover': self.auto_discover,
                'check_message_count': self.check_message_count,
                'category_keywords': self.category_keywords,  # Save category keywords to make them editable
                'sms_notification': {
                    'enabled': SMS_NOTIFICATION_SETTINGS.get('enabled', False),
                    'phone_number': SMS_NOTIFICATION_SETTINGS.get('phone_number'),
                    'min_links': SMS_NOTIFICATION_SETTINGS.get('min_links', 5)
                }
            }
            with open(self.data_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            logger.info("Data saved successfully")
        except Exception as e:
            logger.error(f"Error saving data: {e}")
            
    def set_telegram_token(self, token):
        """Set the Telegram Bot Token"""
        self.telegram_token = token
        
        # Add to the token list if not already there
        if not hasattr(self, 'telegram_tokens'):
            self.telegram_tokens = []
            self.current_token_index = 0
            
        if token not in self.telegram_tokens:
            self.telegram_tokens.append(token)
            
        # Also set in the environment for immediate use
        os.environ["TELEGRAM_BOT_TOKEN"] = token
        self.save_data()
        logger.info("Telegram token set and saved to storage")
        return True
        
    def add_telegram_token(self, token):
        """Add another Telegram Bot Token to the rotation"""
        # Initialize token list if it doesn't exist
        if not hasattr(self, 'telegram_tokens'):
            self.telegram_tokens = []
            self.current_token_index = 0
            
        # Check if this token already exists to prevent duplicates
        if token not in self.telegram_tokens:
            self.telegram_tokens.append(token)
            # If this is the first token, also set it as main token
            if not self.telegram_token:
                self.telegram_token = token
                os.environ["TELEGRAM_BOT_TOKEN"] = token
            self.save_data()
            logger.info(f"Added new token to rotation (total tokens: {len(self.telegram_tokens)})")
            return True
        return False
        
    def remove_telegram_token(self, token):
        """Remove a Telegram Bot Token from the rotation"""
        if not hasattr(self, 'telegram_tokens'):
            return False
            
        if token in self.telegram_tokens:
            self.telegram_tokens.remove(token)
            
            # If we removed the main token, update it to the first available token
            if self.telegram_token == token and self.telegram_tokens:
                self.telegram_token = self.telegram_tokens[0]
                os.environ["TELEGRAM_BOT_TOKEN"] = self.telegram_token
                
            self.save_data()
            logger.info(f"Removed token from rotation (remaining tokens: {len(self.telegram_tokens)})")
            return True
        return False
        
    def get_telegram_token(self):
        """Get the next stored Telegram Bot Token using rotation"""
        # If there are no tokens in the rotation, just return the main token
        if not hasattr(self, 'telegram_tokens') or not self.telegram_tokens:
            return self.telegram_token
        
        # Get the current token index for rotation
        current_index = getattr(self, 'current_token_index', 0)
        
        # Get the token at the current index
        token = self.telegram_tokens[current_index]
        
        # Update the index for the next call (circular rotation)
        next_index = (current_index + 1) % len(self.telegram_tokens)
        self.current_token_index = next_index
        
        logger.debug(f"Using token {current_index+1}/{len(self.telegram_tokens)} in rotation")
        return token
    
    def get_all_telegram_tokens(self):
        """Get all stored Telegram Bot Tokens"""
        if hasattr(self, 'telegram_tokens') and self.telegram_tokens:
            return self.telegram_tokens
        elif self.telegram_token:
            return [self.telegram_token]
        return []
    
    def add_channel(self, channel, category="عمومی"):
        """
        Add a channel to monitor with a specific category
        
        Args:
            channel (str): The channel name or URL
            category (str, optional): The category for this channel. Defaults to "عمومی".
        
        Returns:
            bool: True if added successfully, False if already exists or invalid
        """
        # Normalize channel name
        # Remove @ if present
        if channel.startswith('@'):
            channel = channel[1:]
        
        # Remove https://t.me/ or http://t.me/ if present
        if channel.startswith('https://t.me/'):
            channel = channel[13:]  # Remove 'https://t.me/'
        elif channel.startswith('http://t.me/'):
            channel = channel[12:]  # Remove 'http://t.me/'
        elif channel.startswith('t.me/'):
            channel = channel[5:]   # Remove 't.me/'
            
        # Make sure channel name is valid
        if not channel or '/' in channel:
            logger.warning(f"Invalid channel name: {channel}")
            return False
        
        if channel in self.channels:
            logger.info(f"Channel {channel} already exists")
            return False
        
        self.channels.append(channel)
        # Add channel category
        self.channel_categories[channel] = category
        self.save_data()
        logger.info(f"Added channel: {channel} with category: {category}")
        return True
    
    def remove_channel(self, channel):
        """Remove a channel from monitoring"""
        # Normalize channel name
        # Remove @ if present
        if channel.startswith('@'):
            channel = channel[1:]
        
        # Remove https://t.me/ or http://t.me/ if present
        if channel.startswith('https://t.me/'):
            channel = channel[13:]  # Remove 'https://t.me/'
        elif channel.startswith('http://t.me/'):
            channel = channel[12:]  # Remove 'http://t.me/'
        elif channel.startswith('t.me/'):
            channel = channel[5:]   # Remove 't.me/'
        
        if channel not in self.channels:
            logger.info(f"Channel {channel} not found")
            return False
        
        self.channels.remove(channel)
        # Also remove from channel_categories if exists
        if channel in self.channel_categories:
            del self.channel_categories[channel]
        self.save_data()
        logger.info(f"Removed channel: {channel}")
        return True
    
    def remove_all_channels(self):
        """Remove all channels from monitoring"""
        count = len(self.channels)
        self.channels = []
        # Clear channel categories as well
        self.channel_categories = {}
        self.save_data()
        logger.info(f"Removed all {count} channels")
        return count
    
    def get_channels(self):
        """Get list of monitored channels"""
        return self.channels
    
    def add_link(self, link, channel=None, message_text=None):
        """
        Add a unique link to storage
        
        Args:
            link (str): The link to add
            channel (str, optional): The channel source of the link
            message_text (str, optional): The message text containing the link for keyword analysis
            
        Returns:
            bool: True if the link was new, False if it already existed
        """
        # Normalize link (remove trailing slashes, etc.)
        link = link.strip()
        
        is_new = False
        
        # Check if link is already in the history list
        if link not in self.links:
            self.links.append(link)
            is_new = True
            
            # If it's a new link, add it to the new_links list as well
            if link not in self.new_links:
                self.new_links.append(link)
            
            # Track link count for this channel
            if channel:
                if channel not in self.channel_link_counts:
                    self.channel_link_counts[channel] = 0
                self.channel_link_counts[channel] += 1
            
            # Determine the category based on message content and channel
            category = "عمومی"
            
            # First try to get category from channel
            if channel and channel in self.channel_categories:
                category = self.channel_categories.get(channel, "عمومی")
            
            # Then try to detect category from message text keywords if available
            if message_text:
                detected_category = self._detect_category_from_keywords(message_text)
                if detected_category:
                    category = detected_category
                    logger.debug(f"Detected category '{category}' from message text")
                
                # Check if message mentions a linkdoni channel we don't already monitor
                self._check_for_linkdoni_channels(message_text)
            
            # Initialize category list if needed
            if category not in self.links_by_category:
                self.links_by_category[category] = []
            
            # Add to category if not already there
            if link not in self.links_by_category[category]:
                self.links_by_category[category].append(link)
                logger.debug(f"Added link to category '{category}': {link}")
            
            self.save_data()
            logger.info(f"Added new link: {link} in category '{category}'")
        
        return is_new
        
    def _check_for_linkdoni_channels(self, text):
        """
        Check if text mentions a linkdoni channel that we should add to our monitoring list
        
        Args:
            text (str): Message text to analyze
        """
        if not self.auto_discover or not text:
            return
            
        # Look for telegram links to channels
        # Match patterns like https://t.me/channel_name or @channel_name
        import re
        
        # Look for t.me/[channelname] or @[channelname]
        matches = re.findall(r'(?:https?://)?t\.me/([a-zA-Z0-9_]+)|@([a-zA-Z0-9_]+)', text.lower())
        
        # Extract the matched channel names
        for match in matches:
            # Either the first or second group will have the channel name
            channel_name = match[0] or match[1]
            
            if not channel_name:
                continue
                
            # Skip if this is just a joinchat link
            if channel_name == 'joinchat':
                continue
                
            # Check if this might be a linkdoni channel
            if self._is_linkdoni_channel(channel_name, text):
                # Try to add it (will be ignored if already exists)
                logger.info(f"Auto-discovered potential linkdoni channel: {channel_name}")
                self.add_channel(channel_name, category="لینکدونی")
                
    def _is_linkdoni_channel(self, channel_name, description=None):
        """
        Check if a channel is likely a linkdoni (link sharing) channel
        
        Args:
            channel_name (str): Channel name to check
            description (str, optional): Channel description or context text
            
        Returns:
            bool: True if likely a linkdoni channel
        """
        # Check channel name for linkdoni keywords
        channel_name_lower = channel_name.lower()
        
        # First check channel name itself
        for keyword in self.linkdoni_keywords:
            if keyword.lower() in channel_name_lower:
                return True
                
        # Then check description if provided
        if description:
            description_lower = description.lower()
            
            keyword_count = 0
            for keyword in self.linkdoni_keywords:
                if keyword.lower() in description_lower:
                    keyword_count += 1
                    
            # If at least 2 linkdoni keywords in description, it's likely a linkdoni
            if keyword_count >= 2:
                return True
                
        return False
        
    def _detect_category_from_keywords(self, text):
        """
        Detect the most appropriate category based on keywords in the text
        
        Args:
            text (str): The text to analyze for category keywords
            
        Returns:
            str: The detected category or None if no strong match
        """
        if not text:
            return None
            
        # Convert text to lowercase for case-insensitive matching
        text = text.lower()
        
        # Count keyword matches for each category
        category_scores = {category: 0 for category in self.category_keywords.keys()}
        
        # Check for keywords in each category
        for category, keywords in self.category_keywords.items():
            for keyword in keywords:
                # Case insensitive search
                if keyword.lower() in text:
                    category_scores[category] += 1
        
        # Find category with the most keyword matches
        max_score = 0
        best_category = None
        
        for category, score in category_scores.items():
            if score > max_score:
                max_score = score
                best_category = category
        
        # Only return a category if we have a good match (at least 2 keywords)
        if max_score >= 2:
            return best_category
            
        return None
    
    def get_all_links(self):
        """Get all stored unique links (history)"""
        return self.links
    
    def get_new_links(self):
        """Get only new links from the current session"""
        return self.new_links
        
    def get_links_by_category(self, category=None):
        """
        Get links filtered by category
        
        Args:
            category (str, optional): Category to filter by. If None, returns all categories.
            
        Returns:
            dict: Dictionary of category to links list, or list of links for specific category
        """
        if category:
            return self.links_by_category.get(category, [])
        return self.links_by_category
        
    def get_categories(self):
        """Get all available categories"""
        # Return both default categories and any used categories from links
        categories = set(self.default_categories)
        categories.update(self.links_by_category.keys())
        return sorted(list(categories))
        
    def get_category_keywords(self, category=None):
        """
        Get keywords for a specific category or all category keywords
        
        Args:
            category (str, optional): The category to get keywords for. If None, returns all categories with keywords.
            
        Returns:
            dict or list: Dictionary of all category keywords, or list of keywords for specific category
        """
        if category:
            return self.category_keywords.get(category, [])
        return self.category_keywords
        
    def update_category_keywords(self, category, keywords):
        """
        Update keywords for a specific category
        
        Args:
            category (str): The category to update keywords for
            keywords (list): The new list of keywords for this category
            
        Returns:
            bool: True if successful
        """
        if category in self.category_keywords or category in self.default_categories:
            self.category_keywords[category] = keywords
            self.save_data()
            logger.info(f"Updated keywords for category '{category}'")
            return True
        else:
            logger.warning(f"Cannot update keywords for unknown category: {category}")
            return False
            
    def set_channel_category(self, channel, category):
        """
        Set or update category for a channel
        
        Args:
            channel (str): The channel name
            category (str): The category to assign
            
        Returns:
            bool: True if successful, False if channel not found
        """
        # Normalize channel name first
        if channel.startswith('@'):
            channel = channel[1:]
        
        if channel.startswith('https://t.me/'):
            channel = channel[13:]
        elif channel.startswith('http://t.me/'):
            channel = channel[12:]
        elif channel.startswith('t.me/'):
            channel = channel[5:]
        
        if channel not in self.channels:
            logger.warning(f"Cannot set category for unknown channel: {channel}")
            return False
            
        self.channel_categories[channel] = category
        self.save_data()
        logger.info(f"Set category '{category}' for channel {channel}")
        return True
    
    def clear_links(self):
        """Clear all stored links"""
        self.links = []
        self.new_links = []
        self.links_by_category = {}  # Clear categorized links too
        self.save_data()
        logger.info("All links cleared")
        
    def clear_new_links(self):
        """Clear only the new links list, keeping the history"""
        self.new_links = []
        self.save_data()
        logger.info("New links cleared")
    
    def set_check_interval(self, minutes):
        """Set the check interval in minutes"""
        self.check_interval = minutes
        self.save_data()
        logger.info(f"Check interval set to {minutes} minutes")
    
    def get_check_interval(self):
        """Get the current check interval"""
        return self.check_interval
    
    def update_last_check_time(self):
        """Update the last check timestamp"""
        self.last_check = datetime.now().isoformat()
        self.save_data()
    
    def get_last_check_time(self):
        """Get the last check timestamp"""
        if not self.last_check:
            return None
        
        try:
            dt = datetime.fromisoformat(self.last_check)
            return dt.strftime("%Y-%m-%d %H:%M:%S")
        except Exception:
            return self.last_check
            
    def export_all_links_to_excel(self, filename="all_links.xlsx", category=None):
        """
        Export all links to Excel file
        
        Args:
            filename (str, optional): Output filename. Defaults to "all_links.xlsx".
            category (str, optional): Export only links from this category. Defaults to None (all links).
        
        Returns:
            str: Filename of saved Excel file, or None if export failed
        """
        try:
            if category and category in self.links_by_category:
                # Export links for specific category
                links_to_export = self.links_by_category[category]
                sheet_name = f"Links - {category}"
            else:
                # Export all links
                links_to_export = self.links
                sheet_name = "All Links"
            
            # Create a DataFrame for links
            df = pd.DataFrame(links_to_export, columns=["Link URL"])
            
            # Get the timestamp for the filename
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            category_suffix = f"_{category}" if category else ""
            filename_with_timestamp = f"{filename.split('.')[0]}{category_suffix}_{timestamp}.xlsx"
            
            # Create 'exports' directory if it doesn't exist
            os.makedirs('static/exports', exist_ok=True)
            
            # Save to Excel file
            full_path = os.path.join('static/exports', filename_with_timestamp)
            df.to_excel(full_path, index=False, sheet_name=sheet_name)
            
            logger.info(f"Exported {len(links_to_export)} links to Excel: {full_path}")
            return filename_with_timestamp
            
        except Exception as e:
            logger.error(f"Error exporting links to Excel: {e}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
            return None
            
    def export_new_links_to_excel(self, filename="new_links.xlsx"):
        """Export new links to Excel file"""
        try:
            # Create a DataFrame for new links
            df = pd.DataFrame(self.new_links, columns=["Link URL"])
            
            # Get the timestamp for the filename
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename_with_timestamp = f"{filename.split('.')[0]}_{timestamp}.xlsx"
            
            # Create 'exports' directory if it doesn't exist
            os.makedirs('static/exports', exist_ok=True)
            
            # Save to Excel file
            full_path = os.path.join('static/exports', filename_with_timestamp)
            df.to_excel(full_path, index=False, sheet_name="New Links")
            
            logger.info(f"Exported new links ({len(self.new_links)}) to Excel: {full_path}")
            return filename_with_timestamp
            
        except Exception as e:
            logger.error(f"Error exporting new links to Excel: {e}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
            return None
