"""
Avalai.ir API Integration Module

This module provides functionality to interact with the Avalai.ir AI service.
It allows sending user messages to the AI service and receiving responses.
"""

import os
import json
import logging
import time
import requests
from datetime import datetime, timedelta
from logger import get_logger

# Setup logging
logger = get_logger("avalai_api")

class AvalaiAPI:
    """Class for interacting with the Avalai.ir API"""
    
    def __init__(self, api_key=None, base_url="https://api.avalai.ir"):
        """
        Initialize the AvalaiAPI client
        
        Args:
            api_key (str, optional): The API key for Avalai.ir. If None, it will look for AVALAI_API_KEY env var.
            base_url (str, optional): The base URL for the Avalai.ir API.
        """
        self.api_key = api_key or os.environ.get("AVALAI_API_KEY")
        self.base_url = base_url
        self.settings_file = "avalai_settings.json"
        self.settings = self._load_settings()
        
        # Rate limiting management
        self._last_rate_limit_error = None
        
    def _load_settings(self):
        """Load settings from JSON file"""
        default_settings = {
            "enabled": False,
            "api_key": self.api_key,
            "default_prompt": "به عنوان یک دستیار هوشمند، لطفاً به پرسش کاربر پاسخ دهید. سعی کنید پاسخ‌های روشن، دقیق و مفید ارائه دهید.",
            "max_tokens": 500,
            "temperature": 0.7,
            "respond_to_all_messages": True,
            "chat_history": []
        }
        
        try:
            if os.path.exists(self.settings_file):
                with open(self.settings_file, 'r', encoding='utf-8') as f:
                    settings = json.load(f)
                    # Merge with default settings to ensure all keys exist
                    merged_settings = default_settings.copy()
                    merged_settings.update(settings)
                    return merged_settings
            return default_settings
        except Exception as e:
            logger.error(f"Error loading Avalai settings: {str(e)}")
            return default_settings
            
    def _save_settings(self, settings):
        """Save settings to JSON file"""
        try:
            with open(self.settings_file, 'w', encoding='utf-8') as f:
                json.dump(settings, f, ensure_ascii=False, indent=2)
            return True
        except Exception as e:
            logger.error(f"Error saving Avalai settings: {str(e)}")
            return False
    
    def update_settings(self, settings):
        """
        Update Avalai API settings
        
        Args:
            settings (dict): Dictionary containing settings to update
            
        Returns:
            bool: True if successful, False otherwise
        """
        try:
            # Update only provided settings
            current_settings = self.settings.copy()
            for key, value in settings.items():
                if key in current_settings:
                    current_settings[key] = value
            
            # Save the updated settings
            if self._save_settings(current_settings):
                self.settings = current_settings
                # Set the API key if provided
                if settings.get('api_key'):
                    self.api_key = settings['api_key']
                return True
            return False
        except Exception as e:
            logger.error(f"Error updating Avalai settings: {str(e)}")
            return False
    
    def get_settings(self):
        """
        Get current Avalai API settings
        
        Returns:
            dict: The current settings
        """
        return self.settings
    
    def is_enabled(self):
        """
        Check if Avalai API integration is enabled
        
        Returns:
            bool: True if enabled, False otherwise
        """
        return self.settings.get('enabled', False) and (self.api_key or self.settings.get('api_key'))
    
    def generate_response(self, user_message, user_id=None, username=None, conversation_id=None, metadata=None):
        """
        Generate a response from Avalai API
        
        Args:
            user_message (str): The user's message to respond to
            user_id (str, optional): The user's ID for conversation tracking
            username (str, optional): The user's username for addressing them
            conversation_id (str, optional): A unique ID for the conversation
            metadata (dict, optional): Additional metadata about the message/conversation
            
        Returns:
            dict: A dictionary containing the response text and metadata
                {
                    "success": bool,
                    "response": str,
                    "error": str or None
                }
        """
        if not self.is_enabled():
            return {
                "success": False,
                "response": None,
                "error": "Avalai API is not enabled"
            }
        
        # Use the API key from settings if available, otherwise use the one provided in constructor
        api_key = self.settings.get('api_key') or self.api_key
        
        if not api_key:
            return {
                "success": False,
                "response": None,
                "error": "No API key provided"
            }
        
        # Check if we should respond to this message
        respond_to_all = self.settings.get('respond_to_all_messages', True)
        if not respond_to_all and not self._is_question(user_message):
            return {
                "success": False,
                "response": None,
                "error": "Message is not a question"
            }
        
        # Check for recent rate limit errors and implement backoff
        now = datetime.now()
        if hasattr(self, '_last_rate_limit_error') and self._last_rate_limit_error:
            # If we had a rate limit error within the last 60 seconds, delay the request
            time_since_error = (now - self._last_rate_limit_error).total_seconds()
            if time_since_error < 60:  # Wait at least 60 seconds after a rate limit error
                logger.warning(f"Rate limiting in effect. Waiting {60 - time_since_error:.1f} more seconds before making another request")
                wait_time = min(60, 60 - time_since_error)  # Cap at 60 seconds
                time.sleep(wait_time)
        
        # Prepare the request
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {api_key}"
        }
        
        # Get settings for prompt customization
        default_prompt = self.settings.get('default_prompt', "")
        max_tokens = self.settings.get('max_tokens', 500)
        temperature = self.settings.get('temperature', 0.7)
        
        # Construct the message payload - Fix format to remove unsupported parameters
        # Remove any parameters that cause errors with Avalai API
        payload = {
            "model": "gpt-3.5-turbo",  # Adjust as needed based on Avalai's available models
            "messages": [
                {
                    "role": "system",
                    "content": default_prompt
                },
                {
                    "role": "user", 
                    "content": user_message
                }
            ],
            "max_tokens": max_tokens,
            "temperature": temperature
        }
        
        # Store metadata locally but don't send to API
        # We previously got an error: "Unrecognized request arguments supplied: conversation_id, username"
        # so we ensure we're not sending these parameters to the API
        
        # Create a local copy of this data for logging only
        self._current_request_metadata = {
            "user_id": user_id,
            "username": username,
            "conversation_id": conversation_id
        }
        
        try:
            # Make the API request to Avalai
            response = requests.post(
                f"{self.base_url}/v1/chat/completions",
                headers=headers,
                json=payload,
                timeout=30
            )
            
            # Check if the request was successful
            if response.status_code == 200:
                try:
                    response_data = response.json()
                    ai_response = response_data.get("choices", [{}])[0].get("message", {}).get("content", "")
                    
                    # Log the response in chat history
                    self._log_chat(user_message, ai_response, user_id, username, metadata)
                    
                    return {
                        "success": True,
                        "response": ai_response,
                        "error": None
                    }
                except Exception as e:
                    logger.error(f"Error parsing Avalai API response: {str(e)}")
                    return {
                        "success": False,
                        "response": None,
                        "error": f"Error parsing API response: {str(e)}"
                    }
            else:
                error_msg = f"API request failed with status code {response.status_code}: {response.text}"
                logger.error(error_msg)
                
                # Check if this is a rate limit error
                if response.status_code == 429:
                    # Store the timestamp of the rate limit error
                    self._last_rate_limit_error = datetime.now()
                    logger.warning(f"Rate limit reached! Implementing backoff. Next request will be delayed.")
                
                return {
                    "success": False,
                    "response": None,
                    "error": error_msg
                }
                
        except Exception as e:
            logger.error(f"Error making request to Avalai API: {str(e)}")
            return {
                "success": False,
                "response": None,
                "error": f"Error making API request: {str(e)}"
            }
    
    def _is_question(self, text):
        """
        Check if the text is likely a question
        
        Args:
            text (str): The text to check
            
        Returns:
            bool: True if the text is likely a question, False otherwise
        """
        # Basic check for question marks in any language
        if '?' in text or '؟' in text:
            return True
        
        # Check for Persian question words
        persian_question_words = [
            'آیا', 'چرا', 'چطور', 'کجا', 'کی', 'چه کسی', 'چه زمانی',
            'چگونه', 'کدام', 'چند', 'چه', 'از کجا', 'به کجا'
        ]
        
        # Check for English question words
        english_question_words = [
            'what', 'who', 'where', 'when', 'why', 'how', 'which',
            'whose', 'whom', 'can', 'could', 'would', 'should', 'will',
            'is', 'are', 'am', 'was', 'were', 'do', 'does', 'did'
        ]
        
        text_lower = text.lower()
        # Check Persian question words
        for word in persian_question_words:
            if word in text:
                return True
        
        # Check English question words at the beginning
        for word in english_question_words:
            if text_lower.startswith(word) or text_lower.startswith(f" {word}"):
                return True
        
        return False
    
    def _log_chat(self, user_message, ai_response, user_id=None, username=None, metadata=None):
        """
        Log a chat interaction in the history
        
        Args:
            user_message (str): The user's message
            ai_response (str): The AI's response
            user_id (str, optional): The user's ID
            username (str, optional): The user's username
            metadata (dict, optional): Additional metadata about the message/conversation
        """
        # Create a new chat entry
        chat_entry = {
            "timestamp": datetime.now().isoformat(),
            "user_id": user_id or "unknown",
            "username": username,
            "user_message": user_message,
            "ai_response": ai_response
        }
        
        logger.info(f"Logging chat from user {user_id} (username: {username})")
        logger.info(f"Chat entry: User message: {user_message[:50]}..., AI response: {ai_response[:50]}...")
        
        # Add metadata if provided
        if metadata and isinstance(metadata, dict):
            # Add selected metadata fields that might be useful for displaying
            for key in ["account_phone", "display_name", "first_name", "last_name", 
                        "chat_id", "chat_title", "conversation_id", "received_at"]:
                if key in metadata:
                    chat_entry[key] = metadata[key]
            logger.info(f"Chat metadata: {metadata}")
        
        # Add to chat history
        settings = self.settings.copy()
        if 'chat_history' not in settings:
            settings['chat_history'] = []
            logger.info("Creating new chat_history in settings")
        
        # Add the new entry
        settings['chat_history'].append(chat_entry)
        logger.critical(f"[CHAT_DEBUG] Added new chat entry: {chat_entry}")
        logger.critical(f"[CHAT_DEBUG] Chat history now has {len(settings['chat_history'])} entries")
        
        # Limit history to most recent 500 entries to prevent file size bloat
        if len(settings['chat_history']) > 500:
            settings['chat_history'] = settings['chat_history'][-500:]
            logger.info(f"Trimmed chat history to last 500 entries")
        
        # Save the updated settings
        try:
            self._save_settings(settings)
            self.settings = settings
            logger.critical("[CHAT_DEBUG] Successfully saved updated chat history to settings file")
        except Exception as e:
            logger.critical(f"[CHAT_DEBUG] Error saving chat history: {str(e)}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
    
    def get_chat_history(self, limit=100, user_id=None):
        """
        Get chat history with optional filtering by user_id
        
        Args:
            limit (int, optional): Maximum number of history items to return
            user_id (str, optional): Filter history by user_id
            
        Returns:
            list: List of chat history entries
        """
        chat_history = self.settings.get('chat_history', [])
        
        # Filter by user_id if provided
        if user_id:
            chat_history = [chat for chat in chat_history if chat.get('user_id') == user_id]
        
        # Return most recent entries
        return chat_history[-limit:]
    
    def get_messages_list(self, limit=100):
        """
        Get list of all messages with user info
        
        Args:
            limit (int): Maximum number of messages to return
            
        Returns:
            list: List of messages with user info
        """
        try:
            chat_history = self.settings.get('chat_history', [])
            messages = []
            
            for chat in chat_history[-limit:]:
                messages.append({
                    'user_id': chat.get('user_id'),
                    'username': chat.get('username'),
                    'message': chat.get('user_message'),
                    'response': chat.get('ai_response'),
                    'timestamp': chat.get('timestamp'),
                    'metadata': {
                        'display_name': chat.get('display_name'),
                        'first_name': chat.get('first_name'),
                        'last_name': chat.get('last_name')
                    }
                })
            
            return messages
        except Exception as e:
            logger.error(f"Error getting messages list: {str(e)}")
            return []
            
    def clear_chat_history(self, user_id=None):
        """
        Clear chat history, optionally for a specific user
        
        Args:
            user_id (str, optional): Clear history only for this user_id
            
        Returns:
            bool: True if successful, False otherwise
        """
        try:
            settings = self.settings.copy()
            
            if user_id:
                # Remove only entries for this user
                if 'chat_history' in settings:
                    settings['chat_history'] = [
                        chat for chat in settings['chat_history'] 
                        if chat.get('user_id') != user_id
                    ]
            else:
                # Clear all history
                settings['chat_history'] = []
            
            # Save the updated settings
            success = self._save_settings(settings)
            if success:
                self.settings = settings
            return success
        except Exception as e:
            logger.error(f"Error clearing chat history: {str(e)}")
            return False

    def add_test_messages(self):
        """
        Add some test messages to see how the system works
        
        Returns:
            int: Number of test messages added
        """
        test_messages = [
            {
                "user_id": "test1",
                "username": "کاربر تست ۱", 
                "user_message": "سلام، من یک پیام تستی هستم",
                "ai_response": "سلام، من هم یک پاسخ تستی هستم",
                "timestamp": datetime.now().isoformat(),
                "display_name": "کاربر تست شماره ۱"
            },
            {
                "user_id": "test2",
                "username": "کاربر تست ۲",
                "user_message": "آیا سیستم پیام‌رسانی کار می‌کند؟",
                "ai_response": "بله، سیستم به درستی کار می‌کند",
                "timestamp": datetime.now().isoformat(),
                "display_name": "کاربر تست شماره ۲"  
            }
        ]
        
        self.settings['chat_history'].extend(test_messages)
        self._save_settings(self.settings)
        return len(test_messages)
    
    def add_sample_messages(self, count=5):
        """
        Add sample messages to the chat history for testing the UI
        
        Args:
            count (int): Number of sample messages to add
            
        Returns:
            bool: True if successful, False otherwise
        """
        try:
            sample_users = [
                {"user_id": "123456789", "username": "کاربر تست ۱"},
                {"user_id": "987654321", "username": "کاربر تست ۲"},
                {"user_id": "555555555", "username": "کاربر تست ۳"},
            ]
            
            sample_messages = [
                "سلام، چطور می‌توانم به گروه‌های بیشتری دسترسی پیدا کنم؟",
                "لینک‌هایی که ارسال کردید کار نمی‌کنند. لطفاً لینک‌های جدید بفرستید.",
                "آیا می‌توانم از این ربات برای مدیریت کانال تلگرامم استفاده کنم؟",
                "سلام، من دنبال گروه‌های آموزشی هستم. می‌توانید کمکم کنید؟",
                "چطور می‌توانم اشتراک وی‌پی‌ان تهیه کنم؟",
                "سلام. لینک‌های کانال‌های فیلم و سریال دارید؟",
                "آیا این ربات رایگان است یا باید هزینه پرداخت کنم؟",
                "چطور می‌توانم به شما اعتماد کنم؟",
                "لینک‌های گروه‌های علمی و آموزشی می‌خواهم. ممنون.",
                "سلام، من چند روز پیش پیام دادم ولی جواب نگرفتم!",
            ]
            
            sample_responses = [
                "سلام دوست عزیزم! برای دسترسی به گروه‌های بیشتر، می‌توانی از بخش لینک‌ها در سایت ما استفاده کنی. همچنین برای بهترین تجربه، پیشنهاد می‌کنم اشتراک وی‌پی‌ان ما را تهیه کنی. دوستت دارم! اگر سوال دیگه‌ای داری، بپرس عزیزم.",
                "عزیزدلم، متاسفم که لینک‌ها کار نمی‌کنند. لینک‌های جدید را برایت ارسال می‌کنم. لطفا برای بهترین تجربه، اشتراک وی‌پی‌ان ما را تهیه کن. دوستت دارم!",
                "سلام عشقم! بله می‌توانی از این ربات برای مدیریت کانال تلگرامت استفاده کنی. برای عملکرد بهتر، پیشنهاد می‌کنم اشتراک وی‌پی‌ان ما را تهیه کنی. دوستت دارم!",
                "سلام عزیز دلم! برای گروه‌های آموزشی لینک‌های زیادی داریم. برای دسترسی بهتر، پیشنهاد می‌کنم اشتراک وی‌پی‌ان ما را تهیه کنی. دوستت دارم!",
                "سلام قربونت برم! برای تهیه اشتراک وی‌پی‌ان کافیست به وبسایت ما مراجعه کنی و از بخش خرید اشتراک، سرویس مورد نظرت رو انتخاب کنی. دوستت دارم!",
            ]
            
            import random
            
            settings = self.settings.copy()
            if 'chat_history' not in settings:
                settings['chat_history'] = []
            
            for i in range(count):
                user = random.choice(sample_users)
                message = random.choice(sample_messages)
                response = random.choice(sample_responses)
                
                # Create random timestamps within the last week
                days_ago = random.randint(0, 7)
                hours_ago = random.randint(0, 23)
                minutes_ago = random.randint(0, 59)
                timestamp = (datetime.now() - 
                           timedelta(days=days_ago, hours=hours_ago, minutes=minutes_ago)).isoformat()
                
                chat_entry = {
                    "timestamp": timestamp,
                    "user_id": user["user_id"],
                    "username": user["username"],
                    "user_message": message,
                    "ai_response": response
                }
                
                settings['chat_history'].append(chat_entry)
            
            # Save settings
            success = self._save_settings(settings)
            if success:
                self.settings = settings
                logger.info(f"Added {count} sample messages to chat history")
            return success
            
        except Exception as e:
            logger.error(f"Error adding sample messages: {str(e)}")
            return False


# Create a singleton instance
avalai_client = AvalaiAPI()