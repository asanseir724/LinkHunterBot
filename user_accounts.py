import os
import json
import time
import logging
import hashlib
import asyncio
from telethon import TelegramClient, events
from telethon.errors import SessionPasswordNeededError, FloodWaitError
from telethon.tl.functions.messages import GetDialogsRequest
from telethon.tl.types import InputPeerEmpty, Channel, Chat, User
from telethon.network import ConnectionTcpIntermediate
from datetime import datetime

# Import Avalai API client
from avalai_api import avalai_client

# Configure logging
logger = logging.getLogger(__name__)

class UserAccount:
    """Represents a user Telegram account with its authentication details"""
    
    def __init__(self, phone, api_id="2040", api_hash="b18441a1ff607e10a989891a5462e627", name=None):
        """Initialize a new user account"""
        self.phone = phone
        self.api_id = int(api_id)
        self.api_hash = api_hash
        self.name = name or phone
        self.status = "inactive"  # inactive, active, code_required, 2fa_required, error
        self.connected = False
        self.error = None
        self.client = None
        self.session_file = f"sessions/{self._get_safe_filename(phone)}"
        self.last_check = None
    
    def _get_safe_filename(self, phone):
        """Convert phone number to a safe filename"""
        # Create a safe session filename from the phone number
        return hashlib.md5(phone.encode()).hexdigest()
        
    async def _handle_private_message(self, event):
        """Handle incoming private messages with AI integration"""
        try:
            # Get message information 
            user_id = event.sender_id
            message_text = event.text
            
            # Always log message to chat history
            try:
                sender = await event.get_sender()
                username = sender.username or ""
                first_name = sender.first_name or ""
                last_name = sender.last_name or "" 
                display_name = username or f"{first_name} {last_name}".strip() or f"کاربر {user_id}"
                
                message_metadata = {
                    "user_id": str(user_id),
                    "username": username,
                    "first_name": first_name, 
                    "last_name": last_name,
                    "display_name": display_name,
                    "received_at": datetime.now().isoformat(),
                    "via": "userbot_telethon",
                    "account_phone": self.phone
                }
                
                # Log to Avalai history
                if avalai_client.is_enabled():
                    avalai_client._log_chat(
                        user_message=message_text,
                        ai_response="[پیام از طریق یوزربات دریافت شد]",
                        user_id=str(user_id),
                        username=display_name,
                        metadata=message_metadata
                    )
            except Exception as e:
                logger.error(f"Error saving message to history: {str(e)}")
            
            if not message_text:
                logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Account {self.phone} received empty message from user {user_id}, ignoring")
                return
                
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Account {self.phone} received private message from user {user_id}")
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Message content: {message_text}")
            
            # Get detailed sender information
            try:
                sender = await event.get_sender()
                username = sender.username or ""
                first_name = sender.first_name or ""
                last_name = sender.last_name or ""
                display_name = username or f"{first_name} {last_name}".strip() or f"کاربر {user_id}"
                logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Sender info obtained: {display_name} (username: {username})")
            except Exception as e:
                logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Error getting sender info: {str(e)}")
                username = ""
                first_name = ""
                last_name = ""
                display_name = f"کاربر {user_id}"
            
            # Get chat information
            try:
                chat = await event.get_chat()
                chat_id = chat.id
                chat_title = getattr(chat, 'title', None)
                logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Chat info obtained: ID {chat_id}, Title: {chat_title}")
            except Exception as e:
                logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Error getting chat info: {str(e)}")
                chat_id = user_id
                chat_title = None
            
            # Always log the message in chat history even if we don't respond
            # This ensures we capture all private messages for the admin panel
            conversation_id = f"{self.phone}_{user_id}"
            
            # Prepare metadata for better logging and context
            message_metadata = {
                "user_id": str(user_id),
                "username": username,
                "first_name": first_name,
                "last_name": last_name,
                "display_name": display_name,
                "chat_id": str(chat_id),
                "chat_title": chat_title,
                "account_phone": self.phone,
                "received_at": datetime.now().isoformat(),
                "conversation_id": conversation_id
            }
            
            # Enhanced logging for troubleshooting
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Received message from {display_name} (ID: {user_id}): {message_text[:50]}...")
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Message metadata: {message_metadata}")
            
            # Check if Avalai API integration is enabled
            if not avalai_client.is_enabled():
                logger.critical("[PRIVATE_MESSAGE_DEBUG] Avalai API integration is not enabled, logging message but not responding")
                # Still log the message even without a response
                avalai_client._log_chat(
                    user_message=message_text,
                    ai_response="[پاسخی ارسال نشد - هوش مصنوعی آوالای فعال نیست]",
                    user_id=str(user_id),
                    username=display_name,
                    metadata=message_metadata
                )
                return
                
            # Always respond - Skip the question check for debugging
            settings = avalai_client.get_settings()
            respond_to_all = True  # Force this to True for debugging
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] respond_to_all set to {respond_to_all}")
            
            # Check if this is a question (but we'll respond anyway)
            is_question = "?" in message_text or "؟" in message_text or any(word in message_text.lower() for word in 
                          ["چیست", "چیه", "چگونه", "چطور", "کدام", "کی", "چرا", "آیا", "کجا", 
                           "چند", "کجاست", "چه کسی", "چه زمانی", "چه وقت", "کدوم", 
                           "کی", "میشه", "میتونی", "می‌توانی", "می‌شود", "می‌توان"])
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] is_question determined as: {is_question}")
            
            # For debugging, we'll always log the message even if it's not a question
            # Also, we'll force the system to respond to all messages
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Generating AI response for {display_name}")
            
            # Request AI response - Fix parameters to match the working API call from bot.py
            # Remove conversation_id and username from direct API call (they cause error)
            response_data = avalai_client.generate_response(
                user_message=message_text,
                user_id=str(user_id),
                metadata=message_metadata
            )
            
            if response_data["success"] and response_data["response"]:
                # Send the AI response
                ai_response = response_data["response"]
                logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Sending AI response to user {user_id}: {ai_response[:50]}...")
                await event.respond(ai_response)
            else:
                error = response_data.get("error", "دریافت پاسخ با خطا مواجه شد")
                logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Failed to get AI response: {error}")
                # Still log the message with error response
                avalai_client._log_chat(
                    user_message=message_text,
                    ai_response=f"[خطا در دریافت پاسخ: {error}]",
                    user_id=str(user_id),
                    username=display_name,
                    metadata=message_metadata
                )
                
        except Exception as e:
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Error handling private message: {str(e)}")
            import traceback
            logger.critical(f"[PRIVATE_MESSAGE_DEBUG] Traceback: {traceback.format_exc()}")
    
    async def connect(self, password=None):
        """Connect to Telegram using this account"""
        try:
            # Ensure sessions directory exists
            os.makedirs("sessions", exist_ok=True)
            
            logging.critical(f"[CONNECT_DEEP_DEBUG] Starting connection for account {self.phone}")
            
            # Completely reset any existing client
            if self.client:
                logging.critical(f"[CONNECT_DEEP_DEBUG] Disconnecting existing client for {self.phone}")
                try:
                    await self.client.disconnect()
                    self.client = None
                except Exception as e:
                    logging.critical(f"[CONNECT_DEEP_DEBUG] Error disconnecting existing client: {str(e)}")
            
            # Check if session file exists
            session_path = f"{self.session_file}.session"
            if os.path.exists(session_path):
                logging.critical(f"[CONNECT_DEEP_DEBUG] Session file EXISTS at {session_path}")
                file_size = os.path.getsize(session_path)
                logging.critical(f"[CONNECT_DEEP_DEBUG] Session file size: {file_size} bytes")
                # If file size is too small, it might be corrupted
                if file_size < 100:
                    logging.critical(f"[CONNECT_DEEP_DEBUG] Session file seems corrupted (size: {file_size} bytes), deleting")
                    try:
                        os.remove(session_path)
                        logging.critical(f"[CONNECT_DEEP_DEBUG] Deleted corrupted session file")
                    except Exception as e:
                        logging.critical(f"[CONNECT_DEEP_DEBUG] Error deleting session file: {str(e)}")
            else:
                logging.critical(f"[CONNECT_DEEP_DEBUG] Session file DOES NOT EXIST at {session_path}")
            
            # Create the client with better settings for Replit environment
            logging.critical(f"[CONNECT_DEEP_DEBUG] Creating TelegramClient with api_id={self.api_id}, session_file={self.session_file}")
            self.client = TelegramClient(
                self.session_file,
                self.api_id,
                self.api_hash,
                connection=ConnectionTcpIntermediate,
                retry_delay=1,
                connection_retries=10,
                auto_reconnect=True,
                device_model="Replit Server",
                system_version="Linux",
                app_version="1.0"
            )
            
            # تست برای ارتباط بهتر
            self.client.flood_sleep_threshold = 60
            self.client.request_retries = 10
            
            # تنظیمات حیاتی برای اتصال در محیط Replit
            logging.critical(f"[CONNECT_DEEP_DEBUG] Setting proxy=None")
            self.client.proxy = None  # اطمینان از عدم استفاده از پروکسی
            
            try:
                # پاک کردن هندلرهای قبلی برای جلوگیری از تکرار
                if hasattr(self.client, '_event_builders'):
                    old_handlers_count = len(self.client._event_builders)
                    self.client._event_builders = []
                    logging.critical(f"[CONNECT_DEEP_DEBUG] Cleared {old_handlers_count} previous handlers")
            except Exception as e:
                logging.critical(f"[CONNECT_DEEP_DEBUG] Error clearing handlers: {str(e)}")
            
            # Register message handler for private messages
            logging.critical(f"[CONNECT_DEEP_DEBUG] Registering event handler for {self.phone}")
            @self.client.on(events.NewMessage(incoming=True, func=lambda e: e.is_private))
            async def handle_private_message(event):
                """Handle incoming private messages with AI integration"""
                logging.critical(f"[PRIVATE_MESSAGE_DEBUG] Received private message for account {self.phone}: {event.text}")
                await self._handle_private_message(event)
                
            logging.critical(f"[CONNECT_DEBUG] Registered event handler for NewMessage events for account {self.phone}")
            
            # Connect and check if already authorized
            logging.critical(f"[CONNECT_DEEP_DEBUG] Calling client.connect() for {self.phone}")
            await self.client.connect()
            logging.critical(f"[CONNECT_DEEP_DEBUG] client.connect() completed for {self.phone}")
            
            if await self.client.is_user_authorized():
                self.status = "active"
                self.connected = True
                logging.critical(f"[CONNECT_DEBUG] Successfully authorized account {self.phone}")
                return True, "Already authorized"
            
            # Start the authorization process
            try:
                await self.client.send_code_request(self.phone)
                self.status = "code_required"
                return False, "Authorization code required"
            except FloodWaitError as e:
                self.status = "flood_wait"
                self.error = f"Too many attempts. Try again in {e.seconds} seconds."
                return False, self.error
            except Exception as e:
                self.status = "error"
                self.error = str(e)
                return False, f"Error sending code: {str(e)}"
                
        except Exception as e:
            self.status = "error"
            self.error = str(e)
            return False, f"Connection error: {str(e)}"
    
    async def check_handlers(self):
        """Check if event handlers are properly registered"""
        if not self.client:
            return {"success": False, "handlers": 0, "message": "Client not initialized"}
            
        try:
            # Count number of event handlers
            handlers_count = 0
            
            # Check if client has event handlers attribute
            if hasattr(self.client, '_event_builders'):
                handlers_count = len(self.client._event_builders)
                
            # Log event handlers count
            logging.critical(f"[HANDLERS_DEBUG] Account {self.phone} has {handlers_count} registered event handlers")
                
            return {
                "success": True,
                "handlers": handlers_count, 
                "message": f"Account has {handlers_count} event handlers registered"
            }
        except Exception as e:
            logging.critical(f"[HANDLERS_DEBUG] Error checking handlers: {str(e)}")
            return {"success": False, "handlers": 0, "message": f"Error checking handlers: {str(e)}"}
    
    async def sign_in_with_code(self, code, password=None):
        """Sign in with the received authentication code"""
        import logging
        try:
            if not self.client:
                logging.critical(f"[SIGN_IN_DEBUG] Client not initialized for account {self.phone}")
                return False, "Client not initialized"
            
            logging.critical(f"[SIGN_IN_DEBUG] Signing in with code for {self.phone}, password provided: {bool(password)}")
            
            try:
                # If password is provided alongside the code, attempt to use it for 2FA
                if password:
                    try:
                        # First sign in with the phone code
                        logging.critical(f"[SIGN_IN_DEBUG] Attempting first-stage sign in with code for {self.phone}")
                        await self.client.sign_in(self.phone, code)
                        logging.critical(f"[SIGN_IN_DEBUG] First-stage sign in succeeded, but no 2FA was needed for {self.phone}")
                    except SessionPasswordNeededError:
                        # Now use the password for two-factor authentication
                        logging.critical(f"[SIGN_IN_DEBUG] 2FA needed as expected, using password for {self.phone}")
                        await self.client.sign_in(password=password)
                        logging.critical(f"[SIGN_IN_DEBUG] 2FA sign in succeeded for {self.phone}")
                    
                    self.status = "active"
                    self.connected = True 
                    self.error = None
                    logging.critical(f"[SIGN_IN_DEBUG] Successfully signed in with 2FA for {self.phone}")
                    return True, "Successfully signed in with 2FA"
                else:
                    # Regular sign in without 2FA
                    logging.critical(f"[SIGN_IN_DEBUG] Attempting regular sign in without 2FA for {self.phone}")
                    await self.client.sign_in(self.phone, code)
                    self.status = "active"
                    self.connected = True
                    self.error = None
                    logging.critical(f"[SIGN_IN_DEBUG] Successfully signed in without 2FA for {self.phone}")
                    return True, "Successfully signed in"
                    
            except SessionPasswordNeededError:
                # Two-factor authentication is enabled but no password was provided
                logging.critical(f"[SIGN_IN_DEBUG] 2FA required but no password provided for {self.phone}")
                self.status = "2fa_required"
                return False, "Two-factor authentication required"
            except FloodWaitError as e:
                logging.critical(f"[SIGN_IN_DEBUG] FloodWaitError for {self.phone}: {e.seconds} seconds")
                self.status = "flood_wait"
                self.error = f"Too many attempts. Try again in {e.seconds} seconds."
                return False, self.error
            except Exception as e:
                logging.critical(f"[SIGN_IN_DEBUG] Error signing in for {self.phone}: {str(e)}")
                import traceback
                logging.critical(f"[SIGN_IN_DEBUG] Traceback: {traceback.format_exc()}")
                self.status = "error"
                self.error = str(e)
                return False, f"Error signing in: {str(e)}"
                
        except Exception as e:
            logging.critical(f"[SIGN_IN_DEBUG] Outer exception for {self.phone}: {str(e)}")
            import traceback
            logging.critical(f"[SIGN_IN_DEBUG] Traceback: {traceback.format_exc()}")
            self.status = "error"
            self.error = str(e)
            return False, f"Sign in error: {str(e)}"
    
    async def disconnect(self):
        """Disconnect from Telegram"""
        try:
            if self.client:
                await self.client.disconnect()
            self.status = "inactive"
            self.connected = False
            self.error = None
            return True, "Disconnected successfully"
        except Exception as e:
            self.error = str(e)
            return False, f"Disconnect error: {str(e)}"
    
    async def check_groups_for_links(self, link_manager, max_messages=100):
        """Check all groups this account is a member of for new links"""
        if not self.client or not self.connected:
            logger.warning(f"Account {self.phone} is not connected for checking links")
            return {
                "success": False,
                "error": "Account not connected",
                "new_links": 0,
                "groups_checked": 0,
                "groups_with_links": {}
            }
        
        try:
            # Update last check time
            self.last_check = datetime.now()
            logger.info(f"Starting group check for account {self.phone}")
            
            # Get all dialogs
            logger.info(f"Fetching dialogs for account {self.phone}")
            result = await self.client(GetDialogsRequest(
                offset_date=None,
                offset_id=0,
                offset_peer=InputPeerEmpty(),
                limit=500,
                hash=0
            ))
            
            # Filter for groups and channels
            chats = []
            for dialog in result.dialogs:
                entity = dialog.entity
                if isinstance(entity, (Chat, Channel)) and not entity.broadcast:
                    chats.append(entity)
            
            logger.info(f"Found {len(chats)} groups/chats to check for account {self.phone}")
            
            # Initialize result statistics
            groups_checked = 0
            total_new_links = 0
            groups_with_links = {}
            
            # Check each group
            for chat in chats:
                group_name = getattr(chat, 'title', 'Unknown Group')
                logger.info(f"Checking group: {group_name} for account {self.phone}")
                new_links_in_group = 0
                
                try:
                    # Get messages from the group
                    logger.info(f"Fetching {max_messages} messages from group {group_name}")
                    messages = await self.client.get_messages(chat, limit=max_messages)
                    logger.info(f"Retrieved {len(messages)} messages from group {group_name}")
                    
                    # Look for telegram links in messages
                    for message in messages:
                        if message.message:
                            # Extract links using the link manager
                            found_links = link_manager.extract_links(message.message)
                            
                            if found_links:
                                logger.debug(f"Found {len(found_links)} links in message from {group_name}")
                            
                            for link in found_links:
                                # Add the link and track if it's new
                                if link_manager.add_link(link, group_name, message.message):
                                    logger.info(f"New link found in {group_name}: {link}")
                                    new_links_in_group += 1
                    
                    groups_checked += 1
                    
                    # Only include groups that provided new links
                    if new_links_in_group > 0:
                        logger.info(f"Group {group_name} provided {new_links_in_group} new links")
                        groups_with_links[group_name] = new_links_in_group
                        total_new_links += new_links_in_group
                    else:
                        logger.info(f"No new links found in group {group_name}")
                
                except Exception as e:
                    logger.error(f"Error checking group {group_name}: {str(e)}")
                    continue
            
            return {
                "success": True,
                "new_links": total_new_links,
                "groups_checked": groups_checked,
                "groups_with_links": groups_with_links
            }
            
        except Exception as e:
            logger.error(f"Error checking groups: {str(e)}")
            return {
                "success": False,
                "error": str(e),
                "new_links": 0,
                "groups_checked": 0,
                "groups_with_links": {}
            }
    
    async def get_private_messages(self, limit=30):
        """
        Get the latest private messages received by this account
        
        Args:
            limit (int): Maximum number of dialogs to fetch
            
        Returns:
            dict: Dictionary with success status, messages list and error (if any)
        """
        try:
            if not self.client or not self.connected:
                return {
                    "success": False, 
                    "error": "اکانت متصل نیست", 
                    "messages": []
                }
                
            # Get dialogs (conversations)
            logger.critical(f"[GET_MESSAGES_DEBUG] Getting dialogs for account {self.phone}")
            dialogs = await self.client.get_dialogs(limit=limit)
            logger.critical(f"[GET_MESSAGES_DEBUG] Got {len(dialogs)} dialogs for account {self.phone}")
            
            # Filter to keep only private chats (with users)
            private_chats = [d for d in dialogs if isinstance(d.entity, User)]
            logger.critical(f"[GET_MESSAGES_DEBUG] Filtered to {len(private_chats)} private chats")
            
            all_messages = []
            
            # For each chat, get the latest messages
            for chat in private_chats:
                try:
                    # Get the latest 10 messages from this chat
                    chat_messages = await self.client.get_messages(
                        chat.entity, 
                        limit=10
                    )
                    
                    # Get more info about the chat partner
                    user = await self.client.get_entity(chat.entity)
                    username = user.username or ""
                    first_name = user.first_name or ""
                    last_name = user.last_name or ""
                    display_name = username or f"{first_name} {last_name}".strip() or f"کاربر {user.id}"
                    user_id = user.id
                    
                    logger.critical(f"[GET_MESSAGES_DEBUG] Got {len(chat_messages)} messages with {display_name}")
                    
                    # Format each message
                    for msg in chat_messages:
                        if not msg.text:
                            continue  # Skip non-text messages
                            
                        # Determine whether this message is from the user or from us
                        is_outgoing = msg.out
                        
                        all_messages.append({
                            "text": msg.text,
                            "timestamp": msg.date.isoformat(),
                            "date": msg.date,
                            "is_outgoing": is_outgoing,
                            "chat_id": user_id,
                            "message_id": msg.id,
                            "username": username,
                            "first_name": first_name,
                            "last_name": last_name,
                            "display_name": display_name,
                            "account_phone": self.phone
                        })
                except Exception as chat_error:
                    logger.error(f"Error getting messages from chat {chat.entity.id}: {str(chat_error)}")
                    continue
            
            # Sort all messages by date (newest first)
            all_messages.sort(key=lambda x: x["date"], reverse=True)
            
            return {
                "success": True,
                "messages": all_messages,
                "account": {
                    "phone": self.phone,
                    "name": self.name
                }
            }
            
        except Exception as e:
            logger.error(f"Error getting private messages: {str(e)}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
            return {
                "success": False,
                "error": str(e),
                "messages": []
            }
            
    def to_dict(self):
        """Convert account to dictionary for storage"""
        return {
            "phone": self.phone,
            "api_id": self.api_id,
            "api_hash": self.api_hash,
            "name": self.name,
            "status": self.status,
            "connected": self.connected,
            "error": self.error,
            "last_check": self.last_check.isoformat() if self.last_check else None
        }
    
    @classmethod
    def from_dict(cls, data):
        """Create account from dictionary data"""
        account = cls(
            phone=data["phone"],
            api_id=data["api_id"],
            api_hash=data["api_hash"],
            name=data.get("name")
        )
        account.status = data.get("status", "inactive")
        account.connected = data.get("connected", False)
        account.error = data.get("error")
        
        if data.get("last_check"):
            try:
                account.last_check = datetime.fromisoformat(data["last_check"])
            except:
                account.last_check = None
                
        return account


class AccountManager:
    """Manages multiple Telegram user accounts"""
    
    def __init__(self, accounts_file="accounts_data.json"):
        """Initialize a new account manager"""
        self.accounts_file = accounts_file
        self.accounts = {}
        self.load_accounts()
    
    def load_accounts(self):
        """Load accounts from JSON file"""
        try:
            if os.path.exists(self.accounts_file):
                with open(self.accounts_file, 'r', encoding='utf-8') as f:
                    accounts_data = json.load(f)
                    
                    for phone, account_data in accounts_data.items():
                        self.accounts[phone] = UserAccount.from_dict(account_data)
                        
                logger.info(f"Loaded {len(self.accounts)} accounts from storage")
        except Exception as e:
            logger.error(f"Error loading accounts: {str(e)}")
    
    def save_accounts(self):
        """Save accounts to JSON file"""
        try:
            accounts_data = {}
            for phone, account in self.accounts.items():
                accounts_data[phone] = account.to_dict()
                
            with open(self.accounts_file, 'w', encoding='utf-8') as f:
                json.dump(accounts_data, f, indent=2)
                
            logger.info(f"Saved {len(self.accounts)} accounts to storage")
        except Exception as e:
            logger.error(f"Error saving accounts: {str(e)}")
    
    def add_account(self, phone, api_id, api_hash, name=None):
        """Add a new account"""
        try:
            # Check if account already exists
            if phone in self.accounts:
                return False, "Account with this phone number already exists"
            
            # Create and add the account
            account = UserAccount(phone, api_id, api_hash, name)
            self.accounts[phone] = account
            
            # Save accounts
            self.save_accounts()
            
            return True, "Account added successfully"
        except Exception as e:
            logger.error(f"Error adding account: {str(e)}")
            return False, f"Error adding account: {str(e)}"
    
    def remove_account(self, phone):
        """Remove an account"""
        try:
            # Check if account exists
            if phone not in self.accounts:
                return False, "Account not found"
            
            # Remove the account
            del self.accounts[phone]
            
            # Save accounts
            self.save_accounts()
            
            # Try to remove session file
            try:
                session_filename = f"sessions/{hashlib.md5(phone.encode()).hexdigest()}.session"
                if os.path.exists(session_filename):
                    os.remove(session_filename)
            except:
                pass
            
            return True, "Account removed successfully"
        except Exception as e:
            logger.error(f"Error removing account: {str(e)}")
            return False, f"Error removing account: {str(e)}"
    
    def get_account(self, phone):
        """Get an account by phone number"""
        return self.accounts.get(phone)
    
    def get_all_accounts(self):
        """Get all accounts"""
        return list(self.accounts.values())
    
    def get_active_accounts(self):
        """Get only active/connected accounts"""
        return [account for account in self.accounts.values() if account.connected]
    
    async def connect_all(self):
        """Connect all accounts"""
        results = {}
        for phone, account in self.accounts.items():
            success, message = await account.connect()
            results[phone] = {"success": success, "message": message}
        
        # Save accounts after connection attempts
        self.save_accounts()
        
        return results
    
    async def disconnect_all(self):
        """Disconnect all accounts"""
        results = {}
        for phone, account in self.accounts.items():
            if account.connected:
                success, message = await account.disconnect()
                results[phone] = {"success": success, "message": message}
        
        # Save accounts after disconnection
        self.save_accounts()
        
        return results
    
    async def check_all_accounts_for_links(self, link_manager, max_messages=100):
        """Check all connected accounts for new links"""
        active_accounts = self.get_active_accounts()
        
        logger.info(f"Starting scheduled check of user accounts - Found {len(active_accounts)} active accounts")
        
        if not active_accounts:
            logger.info("No active accounts found for checking groups")
            return {
                "success": True,
                "total_new_links": 0,
                "accounts_checked": 0,
                "accounts_with_links": 0,
                "account_results": {}
            }
        
        account_results = {}
        total_new_links = 0
        accounts_with_links = 0
        total_groups_checked = 0
        
        # Check each active account
        for account in active_accounts:
            logger.info(f"Checking groups for account: {account.phone}")
            try:
                result = await account.check_groups_for_links(link_manager, max_messages)
                account_results[account.phone] = result
                
                # Track total groups checked across all accounts
                groups_checked = result.get("groups_checked", 0)
                total_groups_checked += groups_checked
                
                if result["success"] and result["new_links"] > 0:
                    logger.info(f"Account {account.phone} found {result['new_links']} new links in {len(result['groups_with_links'])} groups")
                    total_new_links += result["new_links"]
                    accounts_with_links += 1
                else:
                    logger.info(f"Account {account.phone} found no new links in {groups_checked} groups")
            except Exception as e:
                logger.error(f"Error checking account {account.phone}: {str(e)}")
                account_results[account.phone] = {
                    "success": False,
                    "error": str(e),
                    "new_links": 0,
                    "groups_checked": 0,
                    "groups_with_links": {}
                }
        
        # Save accounts after checking
        self.save_accounts()
        
        return {
            "success": True,
            "total_new_links": total_new_links,
            "accounts_checked": len(active_accounts),
            "accounts_with_links": accounts_with_links,
            "account_results": account_results,
            "groups_checked": total_groups_checked
        }