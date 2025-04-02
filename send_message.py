import os
import logging
from twilio.rest import Client
from twilio.base.exceptions import TwilioRestException

# Set up logging
logger = logging.getLogger(__name__)

# Get Twilio credentials from environment variables
TWILIO_ACCOUNT_SID = os.environ.get("TWILIO_ACCOUNT_SID")
TWILIO_AUTH_TOKEN = os.environ.get("TWILIO_AUTH_TOKEN")
TWILIO_PHONE_NUMBER = os.environ.get("TWILIO_PHONE_NUMBER")

def send_twilio_message(to_phone_number: str, message: str) -> dict:
    """
    Send an SMS message using Twilio.
    
    Args:
        to_phone_number (str): The recipient's phone number in E.164 format (e.g., +12345678900)
        message (str): The message content to send
        
    Returns:
        dict: A dictionary containing success status and additional information
    """
    # Check if Twilio credentials are available
    if not all([TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_PHONE_NUMBER]):
        error_msg = "Twilio credentials not found in environment variables."
        logger.error(error_msg)
        return {
            "success": False,
            "error": error_msg,
            "message_sid": None
        }
    
    try:
        # Initialize Twilio client
        client = Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN)
        
        # Format recipient number if needed
        if not to_phone_number.startswith('+'):
            to_phone_number = '+' + to_phone_number
        
        # Send the SMS message
        message_obj = client.messages.create(
            body=message,
            from_=TWILIO_PHONE_NUMBER,
            to=to_phone_number
        )
        
        logger.info(f"Message sent with SID: {message_obj.sid}")
        return {
            "success": True,
            "error": None,
            "message_sid": message_obj.sid
        }
    
    except TwilioRestException as e:
        error_msg = f"Twilio error: {str(e)}"
        logger.error(error_msg)
        return {
            "success": False,
            "error": error_msg,
            "message_sid": None
        }
    except Exception as e:
        error_msg = f"Unexpected error sending SMS: {str(e)}"
        logger.error(error_msg)
        return {
            "success": False,
            "error": error_msg,
            "message_sid": None
        }

def send_notification(to_phone_number: str, new_links_count: int, channel: str = None) -> dict:
    """
    Send a notification about new links found.
    
    Args:
        to_phone_number (str): The recipient's phone number in E.164 format
        new_links_count (int): Number of new links found
        channel (str, optional): Channel name where links were found
        
    Returns:
        dict: Result of the send operation
    """
    # Create the notification message
    if channel:
        message = f"🔔 {new_links_count} new links found in channel {channel}. Check the dashboard for details."
    else:
        message = f"🔔 {new_links_count} new links found across all monitored channels. Check the dashboard for details."
    
    # Send the message
    return send_twilio_message(to_phone_number, message)