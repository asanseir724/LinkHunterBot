"""
Script to display messages and responses from avalai_settings.json
"""

import json
from datetime import datetime

def get_messages_with_responses(limit=100):
    """
    Get list of all messages with their responses
    
    Args:
        limit (int): Maximum number of messages to return
        
    Returns:
        list: List of messages with responses
    """
    try:
        with open('avalai_settings.json', 'r', encoding='utf-8') as file:
            settings = json.load(file)
        
        chat_history = settings.get('chat_history', [])
        return chat_history[-limit:]
    except Exception as e:
        print(f"Error retrieving messages: {e}")
        return []

def is_sample_message(msg):
    """
    Check if a message is a sample/test message based on certain criteria
    
    Args:
        msg (dict): The message dictionary
        
    Returns:
        bool: True if it's a sample message, False otherwise
    """
    # List of common test usernames
    test_usernames = ['کاربر تست ۱', 'کاربر تست ۲', 'کاربر تست ۳', 
                      'Test User', 'Sample User', 'Demo User']
    
    username = msg.get('username', '')
    user_message = msg.get('user_message', '')
    
    # Check if username is in test list
    if any(test_name in username for test_name in test_usernames):
        return True
    
    # Check for sample message indicators
    if 'test' in username.lower() or 'sample' in username.lower():
        return True
        
    # Check if message contains sample phrases
    sample_phrases = ['test message', 'sample message', 'this is a test']
    if any(phrase in user_message.lower() for phrase in sample_phrases):
        return True
        
    return False

if __name__ == "__main__":
    messages = get_messages_with_responses()
    print(f"Found {len(messages)} messages\n")
    
    # Separate real and sample messages
    real_messages = []
    sample_messages = []
    
    for msg in messages:
        if is_sample_message(msg):
            sample_messages.append(msg)
        else:
            real_messages.append(msg)
    
    # Display real user messages
    print("-" * 80)
    print(f"REAL USER MESSAGES: ({len(real_messages)} found)")
    print("-" * 80)
    
    for msg in real_messages:
        timestamp = msg.get('timestamp', '').split('T')[0] if msg.get('timestamp') else 'Unknown date'
        username = msg.get('username', 'Unknown')
        user_message = msg.get('user_message', 'No message')
        ai_response = msg.get('ai_response', 'No response')
        
        print(f"Date: {timestamp}")
        print(f"User: {username}")
        print(f"Message: {user_message}")
        print(f"Response: {ai_response}")
        print("-" * 80)
    
    # Display sample messages
    print("\n" + "-" * 80)
    print(f"SAMPLE MESSAGES: ({len(sample_messages)} found)")
    print("-" * 80)
    
    for msg in sample_messages:
        timestamp = msg.get('timestamp', '').split('T')[0] if msg.get('timestamp') else 'Unknown date'
        username = msg.get('username', 'Unknown')
        user_message = msg.get('user_message', 'No message')
        ai_response = msg.get('ai_response', 'No response')
        
        print(f"Date: {timestamp}")
        print(f"User: {username}")
        print(f"Message: {user_message}")
        print(f"Response: {ai_response}")
        print("-" * 80)