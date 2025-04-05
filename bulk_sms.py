import os
import logging
import json
import time
from twilio.rest import Client
from twilio.base.exceptions import TwilioRestException
from typing import List, Dict, Any

# Set up logging
from logger import get_logger
logger = get_logger(__name__)

# Get Twilio credentials from environment variables
TWILIO_ACCOUNT_SID = os.environ.get("TWILIO_ACCOUNT_SID")
TWILIO_AUTH_TOKEN = os.environ.get("TWILIO_AUTH_TOKEN")
TWILIO_PHONE_NUMBER = os.environ.get("TWILIO_PHONE_NUMBER")

class BulkSMSSender:
    """
    Class for sending SMS messages to multiple recipients in bulk.
    
    Attributes:
        client: Twilio Client instance for sending messages
        log_file: Path to the log file for successful and failed messages
        delay: Delay in seconds between sending messages to prevent rate limiting
    """
    
    def __init__(self, log_file="logs/bulk_sms_log.json", delay=1):
        """
        Initialize the BulkSMSSender.
        
        Args:
            log_file (str): Path to the log file for storing message logs
            delay (int): Delay in seconds between sending messages to prevent rate limiting
        """
        # Check if Twilio credentials are available
        if not all([TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_PHONE_NUMBER]):
            error_msg = "Twilio credentials not found in environment variables."
            logger.error(error_msg)
            raise ValueError(error_msg)
        
        # Initialize Twilio client
        self.client = Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN)
        self.log_file = log_file
        self.delay = delay
        
        # Ensure logs directory exists
        os.makedirs(os.path.dirname(log_file), exist_ok=True)

    def send_bulk_messages(self, recipients: List[str], message: str, 
                         use_delay: bool = True) -> Dict[str, Any]:
        """
        Send the same message to multiple recipients.
        
        Args:
            recipients (List[str]): List of phone numbers to send the message to
            message (str): The message content to send
            use_delay (bool): Whether to use a delay between sending messages
            
        Returns:
            Dict: Results of the bulk send operation, including counts and logs
        """
        results = {
            "total": len(recipients),
            "successful": 0,
            "failed": 0,
            "logs": []
        }
        
        for phone in recipients:
            # Format recipient number if needed
            if phone and not phone.startswith('+'):
                phone = '+' + phone
                
            # Skip empty or invalid numbers
            if not phone or len(phone) < 8:
                logger.warning(f"Skipping invalid phone number: {phone}")
                results["logs"].append({
                    "phone": phone,
                    "status": "skipped",
                    "error": "Invalid phone number"
                })
                results["failed"] += 1
                continue
                
            try:
                # Send the SMS message
                message_obj = self.client.messages.create(
                    body=message,
                    from_=TWILIO_PHONE_NUMBER,
                    to=phone
                )
                
                logger.info(f"Message sent to {phone} with SID: {message_obj.sid}")
                results["logs"].append({
                    "phone": phone,
                    "status": "success",
                    "message_sid": message_obj.sid,
                    "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
                })
                results["successful"] += 1
                
                # Add delay between messages to prevent rate limiting
                if use_delay and self.delay > 0:
                    time.sleep(self.delay)
                    
            except TwilioRestException as e:
                error_msg = f"Twilio error sending to {phone}: {str(e)}"
                logger.error(error_msg)
                results["logs"].append({
                    "phone": phone,
                    "status": "failed",
                    "error": str(e),
                    "error_code": e.code if hasattr(e, 'code') else None,
                    "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
                })
                results["failed"] += 1
                
            except Exception as e:
                error_msg = f"Unexpected error sending to {phone}: {str(e)}"
                logger.error(error_msg)
                results["logs"].append({
                    "phone": phone,
                    "status": "failed",
                    "error": str(e),
                    "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
                })
                results["failed"] += 1
        
        # Save logs to file
        self._save_logs(results["logs"])
        
        return results
    
    def send_personalized_messages(self, 
                                 message_data: List[Dict[str, str]],
                                 use_delay: bool = True) -> Dict[str, Any]:
        """
        Send personalized messages to multiple recipients.
        
        Args:
            message_data (List[Dict]): List of dictionaries with 'phone' and 'message' keys
                [{'phone': '+1234567890', 'message': 'Hello John!'}, ...]
            use_delay (bool): Whether to use a delay between sending messages
            
        Returns:
            Dict: Results of the bulk send operation, including counts and logs
        """
        results = {
            "total": len(message_data),
            "successful": 0,
            "failed": 0,
            "logs": []
        }
        
        for data in message_data:
            phone = data.get('phone', '')
            message = data.get('message', '')
            
            # Format recipient number if needed
            if phone and not phone.startswith('+'):
                phone = '+' + phone
                
            # Skip empty or invalid numbers or messages
            if not phone or not message:
                logger.warning(f"Skipping invalid data: {data}")
                results["logs"].append({
                    "phone": phone,
                    "status": "skipped",
                    "error": "Invalid phone number or empty message"
                })
                results["failed"] += 1
                continue
                
            try:
                # Send the SMS message
                message_obj = self.client.messages.create(
                    body=message,
                    from_=TWILIO_PHONE_NUMBER,
                    to=phone
                )
                
                logger.info(f"Personalized message sent to {phone} with SID: {message_obj.sid}")
                results["logs"].append({
                    "phone": phone,
                    "status": "success", 
                    "message_sid": message_obj.sid,
                    "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
                })
                results["successful"] += 1
                
                # Add delay between messages to prevent rate limiting
                if use_delay and self.delay > 0:
                    time.sleep(self.delay)
                    
            except TwilioRestException as e:
                error_msg = f"Twilio error sending to {phone}: {str(e)}"
                logger.error(error_msg)
                results["logs"].append({
                    "phone": phone,
                    "status": "failed",
                    "error": str(e),
                    "error_code": e.code if hasattr(e, 'code') else None,
                    "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
                })
                results["failed"] += 1
                
            except Exception as e:
                error_msg = f"Unexpected error sending to {phone}: {str(e)}"
                logger.error(error_msg)
                results["logs"].append({
                    "phone": phone,
                    "status": "failed",
                    "error": str(e),
                    "timestamp": time.strftime("%Y-%m-%d %H:%M:%S")
                })
                results["failed"] += 1
        
        # Save logs to file
        self._save_logs(results["logs"])
        
        return results
    
    def _save_logs(self, new_logs):
        """
        Save message logs to the log file.
        
        Args:
            new_logs (List): New logs to append to the existing log file
        """
        try:
            # Create logs directory if it doesn't exist
            os.makedirs(os.path.dirname(self.log_file), exist_ok=True)
            
            # Read existing logs if they exist
            existing_logs = []
            try:
                if os.path.exists(self.log_file):
                    with open(self.log_file, 'r', encoding='utf-8') as f:
                        existing_logs = json.load(f)
            except Exception as e:
                logger.warning(f"Error reading existing logs: {str(e)}")
            
            # Append new logs to existing logs
            all_logs = existing_logs + new_logs
            
            # Write back to the file
            with open(self.log_file, 'w', encoding='utf-8') as f:
                json.dump(all_logs, f, ensure_ascii=False, indent=2)
                
            logger.debug(f"Saved {len(new_logs)} SMS logs to {self.log_file}")
            
        except Exception as e:
            logger.error(f"Error saving SMS logs: {str(e)}")
    
    def get_logs(self, limit=100, status=None):
        """
        Get SMS message logs from the log file.
        
        Args:
            limit (int): Maximum number of logs to return
            status (str, optional): Filter logs by status ('success', 'failed', 'skipped')
            
        Returns:
            List: Message logs
        """
        try:
            if not os.path.exists(self.log_file):
                return []
                
            with open(self.log_file, 'r', encoding='utf-8') as f:
                logs = json.load(f)
            
            # Filter by status if specified
            if status:
                logs = [log for log in logs if log.get('status') == status]
            
            # Return the most recent logs up to the limit
            return logs[-limit:]
            
        except Exception as e:
            logger.error(f"Error reading SMS logs: {str(e)}")
            return []


# Function to create and get a new bulk SMS sender instance
def get_bulk_sms_sender():
    """
    Create and return a new BulkSMSSender instance.
    
    Returns:
        BulkSMSSender: A new instance of the BulkSMSSender class
    """
    try:
        return BulkSMSSender()
    except ValueError as e:
        logger.error(f"Failed to create BulkSMSSender: {str(e)}")
        return None