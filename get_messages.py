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

if __name__ == "__main__":
    messages = get_messages_with_responses()
    print(f"Found {len(messages)} messages\n")
    
    print("-" * 80)
    print("REAL USER MESSAGES:")
    print("-" * 80)
    real_users = [msg for msg in messages if msg.get('username') not in ['کاربر تست ۱', 'کاربر تست ۲', 'کاربر تست ۳']]
    
    for msg in real_users:
        timestamp = msg.get('timestamp', '').split('T')[0]
        username = msg.get('username', 'Unknown')
        user_message = msg.get('user_message', 'No message')
        ai_response = msg.get('ai_response', 'No response')
        
        print(f"Date: {timestamp}")
        print(f"User: {username}")
        print(f"Message: {user_message}")
        print(f"Response: {ai_response}")
        print("-" * 80)