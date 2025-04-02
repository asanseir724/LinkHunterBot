import os
import logging
from link_manager import SMS_NOTIFICATION_SETTINGS
from logger import get_logger

# Get logger
logger = get_logger(__name__)

def update_sms_settings(enabled, phone_number=None, min_links=5):
    """
    Update the SMS notification settings
    
    Args:
        enabled (bool): Whether SMS notifications are enabled
        phone_number (str, optional): The phone number to send notifications to
        min_links (int, optional): Minimum number of links required to trigger a notification
        
    Returns:
        bool: True if successful, False otherwise
    """
    global SMS_NOTIFICATION_SETTINGS
    
    try:
        # Check if Twilio credentials are configured
        twilio_sid = os.environ.get("TWILIO_ACCOUNT_SID")
        twilio_token = os.environ.get("TWILIO_AUTH_TOKEN")
        twilio_phone = os.environ.get("TWILIO_PHONE_NUMBER")
        
        SMS_NOTIFICATION_SETTINGS.update({
            'enabled': enabled,
            'phone_number': phone_number,
            'min_links': min_links,
            'twilio_configured': all([twilio_sid, twilio_token, twilio_phone])
        })
        
        logger.info(f"SMS notification settings updated: enabled={enabled}, phone={phone_number}, min_links={min_links}")
        return True
    except Exception as e:
        logger.error(f"Error updating SMS notification settings: {str(e)}")
        return False
        
def get_sms_settings():
    """
    Get the current SMS notification settings
    
    Returns:
        dict: The current SMS notification settings
    """
    return {
        'enabled': SMS_NOTIFICATION_SETTINGS.get('enabled', False),
        'phone_number': SMS_NOTIFICATION_SETTINGS.get('phone_number'),
        'min_links': SMS_NOTIFICATION_SETTINGS.get('min_links', 5),
        'twilio_configured': SMS_NOTIFICATION_SETTINGS.get('twilio_configured', False)
    }
    
def should_send_notification(new_links_count):
    """
    Determine if a notification should be sent based on the number of new links found
    
    Args:
        new_links_count (int): Number of new links found
        
    Returns:
        bool: True if a notification should be sent, False otherwise
    """
    settings = get_sms_settings()
    
    # Check if notifications are enabled
    if not settings['enabled']:
        return False
        
    # Check if Twilio is configured
    if not settings['twilio_configured']:
        logger.warning("SMS notifications enabled but Twilio is not configured")
        return False
        
    # Check if we have a phone number to send to
    if not settings['phone_number']:
        logger.warning("SMS notifications enabled but no phone number is configured")
        return False
        
    # Check if we have enough new links to trigger a notification
    if new_links_count < settings['min_links']:
        logger.debug(f"Not enough new links to send notification: {new_links_count} < {settings['min_links']}")
        return False
        
    return True