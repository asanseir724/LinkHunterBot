# Link Hunter Bot (ربات لینک‌یاب تلگرام)

A comprehensive Telegram bot designed to automatically extract and store links from Telegram channels. The bot monitors specified channels, extracts links from messages, and provides a web interface for managing the collected links.

## Features

- **Automated Link Extraction**: Monitors Telegram channels and extracts links automatically
- **Category-based Link Organization**: Classifies links into categories based on channel or content
- **Web Dashboard**: Full-featured web interface for managing channels, links, and settings
- **Excel Export**: Export links to Excel format with timestamps
- **Token Rotation**: Support for multiple Telegram Bot tokens to avoid rate limits
- **User Account Integration**: Support for adding Telegram user accounts to access private channels
- **SMS Notifications**: Optional SMS notifications via Twilio when new links are found
- **Auto-discovery**: Automatically finds and adds new link-sharing channels

## Requirements

- Python 3.8+
- Flask
- Telethon
- Twilio (optional, for SMS notifications)
- PostgreSQL (optional, for larger deployments)

## Setup Instructions

### 1. Clone the Repository

```bash
git clone https://github.com/asanseir724/LinkHunterBot.git
cd LinkHunterBot
```

### 2. Install Dependencies

```bash
pip install -r dependencies.txt
```

### 3. Set Environment Variables

Create a `.env` file in the project root with the following variables:

```
SESSION_SECRET=your_session_secret
TELEGRAM_BOT_TOKEN=your_telegram_bot_token

# For SMS notifications (optional)
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_PHONE_NUMBER=your_twilio_phone_number
```

### 4. Run the Application

```bash
python main.py
```

The web interface will be available at `http://localhost:5000`.

## Configuration

1. **Bot Token**: Obtain a bot token from [@BotFather](https://t.me/BotFather) and set it in the `.env` file or through the settings page
2. **Add Channels**: Add Telegram channels to monitor through the web interface
3. **Configure Intervals**: Set how frequently the bot checks for new links
4. **SMS Notifications**: Optionally configure SMS notifications for when new links are found

## User Accounts (Optional)

To monitor private channels, you can add user accounts:

1. Go to the Accounts page
2. Add a new account with phone number, API ID, and API Hash
3. Complete the authorization process

## SMS Notifications (Optional)

To enable SMS notifications:

1. Create a Twilio account and get credentials
2. Set environment variables for Twilio
3. Configure notification settings in the web interface

## Project Structure

- `main.py`: Main application entry point
- `bot.py`: Telegram bot functionality
- `link_manager.py`: Link extraction and management
- `user_accounts.py`: User account integration
- `notification_utils.py`: Notification system
- `templates/`: Web interface templates

## License

This project is open source software licensed under the MIT license.

## Support

For questions, issues, or contributions, please open an issue on GitHub or contact the repository owner.