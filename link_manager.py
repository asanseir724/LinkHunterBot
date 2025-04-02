import json
import os
from datetime import datetime
from logger import get_logger

# Get module logger
logger = get_logger(__name__)

class LinkManager:
    """Manages link extraction, storage, and monitoring of channels"""
    
    def __init__(self, data_file="links_data.json"):
        self.data_file = data_file
        self.channels = []  # List of channels to monitor
        self.links = []     # List of unique links
        self.check_interval = 30  # Default check interval in minutes
        self.last_check = None
        
        # Load data from file if exists
        self.load_data()
    
    def load_data(self):
        """Load data from JSON file if it exists"""
        if os.path.exists(self.data_file):
            try:
                with open(self.data_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    self.channels = data.get('channels', [])
                    self.links = data.get('links', [])
                    self.check_interval = data.get('check_interval', 30)
                    self.last_check = data.get('last_check')
                logger.info(f"Loaded data: {len(self.channels)} channels, {len(self.links)} links")
            except Exception as e:
                logger.error(f"Error loading data: {e}")
    
    def save_data(self):
        """Save data to JSON file"""
        try:
            data = {
                'channels': self.channels,
                'links': self.links,
                'check_interval': self.check_interval,
                'last_check': self.last_check
            }
            with open(self.data_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            logger.info("Data saved successfully")
        except Exception as e:
            logger.error(f"Error saving data: {e}")
    
    def add_channel(self, channel):
        """Add a channel to monitor"""
        # Normalize channel name (remove @ if present)
        if channel.startswith('@'):
            channel = channel[1:]
        
        if channel in self.channels:
            logger.info(f"Channel {channel} already exists")
            return False
        
        self.channels.append(channel)
        self.save_data()
        logger.info(f"Added channel: {channel}")
        return True
    
    def remove_channel(self, channel):
        """Remove a channel from monitoring"""
        # Normalize channel name (remove @ if present)
        if channel.startswith('@'):
            channel = channel[1:]
        
        if channel not in self.channels:
            logger.info(f"Channel {channel} not found")
            return False
        
        self.channels.remove(channel)
        self.save_data()
        logger.info(f"Removed channel: {channel}")
        return True
    
    def get_channels(self):
        """Get list of monitored channels"""
        return self.channels
    
    def add_link(self, link):
        """Add a unique link to storage"""
        # Normalize link (remove trailing slashes, etc.)
        link = link.strip()
        
        if link in self.links:
            return False
        
        self.links.append(link)
        self.save_data()
        logger.info(f"Added new link: {link}")
        return True
    
    def get_all_links(self):
        """Get all stored unique links"""
        return self.links
    
    def clear_links(self):
        """Clear all stored links"""
        self.links = []
        self.save_data()
        logger.info("All links cleared")
    
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
