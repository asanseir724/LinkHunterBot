import os
import json
import time
import logging
import hashlib
import asyncio
from telethon import TelegramClient
from telethon.errors import SessionPasswordNeededError, FloodWaitError
from telethon.tl.functions.messages import GetDialogsRequest
from telethon.tl.types import InputPeerEmpty, Channel, Chat, User
from datetime import datetime

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
    
    async def connect(self, password=None):
        """Connect to Telegram using this account"""
        try:
            # Ensure sessions directory exists
            os.makedirs("sessions", exist_ok=True)
            
            # Create the client
            self.client = TelegramClient(self.session_file, self.api_id, self.api_hash)
            
            # Connect and check if already authorized
            await self.client.connect()
            
            if await self.client.is_user_authorized():
                self.status = "active"
                self.connected = True
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
    
    async def sign_in_with_code(self, code, password=None):
        """Sign in with the received authentication code"""
        try:
            if not self.client:
                return False, "Client not initialized"
            
            try:
                # If password is provided alongside the code, attempt to use it for 2FA
                if password:
                    try:
                        # First sign in with the phone code
                        await self.client.sign_in(self.phone, code)
                    except SessionPasswordNeededError:
                        # Now use the password for two-factor authentication
                        await self.client.sign_in(password=password)
                    
                    self.status = "active"
                    self.connected = True 
                    self.error = None
                    return True, "Successfully signed in with 2FA"
                else:
                    # Regular sign in without 2FA
                    await self.client.sign_in(self.phone, code)
                    self.status = "active"
                    self.connected = True
                    self.error = None
                    return True, "Successfully signed in"
                    
            except SessionPasswordNeededError:
                # Two-factor authentication is enabled but no password was provided
                self.status = "2fa_required"
                return False, "Two-factor authentication required"
            except FloodWaitError as e:
                self.status = "flood_wait"
                self.error = f"Too many attempts. Try again in {e.seconds} seconds."
                return False, self.error
            except Exception as e:
                self.status = "error"
                self.error = str(e)
                return False, f"Error signing in: {str(e)}"
                
        except Exception as e:
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
            
            # Get all dialogs
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
            
            # Initialize result statistics
            groups_checked = 0
            total_new_links = 0
            groups_with_links = {}
            
            # Check each group
            for chat in chats:
                group_name = getattr(chat, 'title', 'Unknown Group')
                new_links_in_group = 0
                
                try:
                    # Get messages from the group
                    messages = await self.client.get_messages(chat, limit=max_messages)
                    
                    # Look for telegram links in messages
                    for message in messages:
                        if message.message:
                            # Extract links using the link manager
                            found_links = link_manager.extract_links(message.message)
                            
                            for link in found_links:
                                # Add the link and track if it's new
                                if link_manager.add_link(link, group_name, message.message):
                                    new_links_in_group += 1
                    
                    groups_checked += 1
                    
                    # Only include groups that provided new links
                    if new_links_in_group > 0:
                        groups_with_links[group_name] = new_links_in_group
                        total_new_links += new_links_in_group
                
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
        
        if not active_accounts:
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
        
        # Check each active account
        for account in active_accounts:
            try:
                result = await account.check_groups_for_links(link_manager, max_messages)
                account_results[account.phone] = result
                
                if result["success"] and result["new_links"] > 0:
                    total_new_links += result["new_links"]
                    accounts_with_links += 1
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
            "account_results": account_results
        }