"""
Test script to retrieve messages from avalai_settings.json
"""

import json
from datetime import datetime

def get_messages_list(limit=100):
    """
    Get list of all messages with user info
    
    Args:
        limit (int): Maximum number of messages to return
        
    Returns:
        list: List of messages with user info
    """
    try:
        with open('avalai_settings.json', 'r', encoding='utf-8') as file:
            settings = json.load(file)
        
        chat_history = settings.get('chat_history', [])
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
        print(f"Error retrieving messages: {e}")
        return []

if __name__ == "__main__":
    messages = get_messages_list()
    print(f"Found {len(messages)} messages")
    for msg in messages:
        print(f"User {msg['username']}: {msg['message']}")