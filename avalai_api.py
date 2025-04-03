"""
Avalai.ir API Integration Module

This module provides functionality to interact with the Avalai.ir AI service.
It allows sending user messages to the AI service and receiving responses.
"""

import os
import json
import logging
import datetime
import requests
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
        
        # Prepare the request
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {api_key}"
        }
        
        # Get settings for prompt customization
        default_prompt = self.settings.get('default_prompt', "")
        max_tokens = self.settings.get('max_tokens', 500)
        temperature = self.settings.get('temperature', 0.7)
        
        # Construct the message payload
        # The actual API request structure may need to be adjusted based on Avalai's API docs
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
        
        # Add metadata if provided
        if user_id:
            payload["user_id"] = user_id
        if username:
            payload["username"] = username
        if conversation_id:
            payload["conversation_id"] = conversation_id
        
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
            "timestamp": datetime.datetime.now().isoformat(),
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
        logger.info(f"Added new chat entry. Chat history now has {len(settings['chat_history'])} entries")
        
        # Limit history to most recent 500 entries to prevent file size bloat
        if len(settings['chat_history']) > 500:
            settings['chat_history'] = settings['chat_history'][-500:]
            logger.info(f"Trimmed chat history to last 500 entries")
        
        # Save the updated settings
        try:
            self._save_settings(settings)
            self.settings = settings
            logger.info("Successfully saved updated chat history to settings file")
        except Exception as e:
            logger.error(f"Error saving chat history: {str(e)}")
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

# Create a singleton instance
avalai_client = AvalaiAPI()