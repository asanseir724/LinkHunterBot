"""
Script to add sample messages to avalai_settings.json for testing
"""

from avalai_api import avalai_client

# Add sample messages
num_added = avalai_client.add_sample_messages(count=10)
print(f"Added {num_added} sample messages to chat history")