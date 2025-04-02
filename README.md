# Link Hunter Bot (ربات لینک‌یاب تلگرام)

A comprehensive Telegram bot designed to automatically extract and store links from Telegram channels. The bot monitors specified channels, extracts links from messages, and provides a web interface for managing the collected links.

ربات قدرتمند تلگرام برای استخراج و ذخیره خودکار لینک‌ها از کانال‌های تلگرام. این ربات کانال‌های مشخص شده را بررسی کرده، لینک‌ها را از پیام‌ها استخراج می‌کند و یک رابط وب برای مدیریت لینک‌های جمع‌آوری شده ارائه می‌دهد.

## Features (ویژگی‌ها)

- **Automated Link Extraction**: Monitors Telegram channels and extracts links automatically
- **Category-based Link Organization**: Classifies links into categories based on channel or content
- **Web Dashboard**: Full-featured web interface for managing channels, links, and settings
- **Excel Export**: Export links to Excel format with timestamps
- **Token Rotation**: Support for multiple Telegram Bot tokens to avoid rate limits
- **User Account Integration**: Support for adding Telegram user accounts to access private channels
- **SMS Notifications**: Optional SMS notifications via Twilio when new links are found
- **Auto-discovery**: Automatically finds and adds new link-sharing channels
- **Keyword Management**: Manage category keywords for automatic link classification

- **استخراج خودکار لینک**: نظارت بر کانال‌های تلگرام و استخراج خودکار لینک‌ها
- **دسته‌بندی لینک‌ها**: طبقه‌بندی لینک‌ها در دسته‌های مختلف بر اساس کانال یا محتوا
- **داشبورد وب**: رابط کاربری وب کامل برای مدیریت کانال‌ها، لینک‌ها و تنظیمات
- **صدور اکسل**: صدور لینک‌ها به فرمت اکسل همراه با برچسب زمانی
- **چرخش توکن**: پشتیبانی از چندین توکن ربات تلگرام برای جلوگیری از محدودیت‌های نرخ
- **یکپارچه‌سازی حساب کاربری**: پشتیبانی از افزودن حساب‌های کاربری تلگرام برای دسترسی به کانال‌های خصوصی
- **اطلاع‌رسانی پیامکی**: اطلاع‌رسانی پیامکی اختیاری از طریق Twilio هنگام یافتن لینک‌های جدید
- **کشف خودکار**: یافتن و افزودن خودکار کانال‌های اشتراک‌گذاری لینک جدید
- **مدیریت کلمات کلیدی**: مدیریت کلمات کلیدی دسته‌بندی‌ها برای طبقه‌بندی خودکار لینک‌ها

## Requirements (پیش‌نیازها)

- Python 3.8+
- Flask
- Telethon
- Twilio (optional, for SMS notifications)
- PostgreSQL (optional, for larger deployments)

## Setup Instructions (راهنمای نصب و راه‌اندازی)

### 1. Clone the Repository (کلون کردن مخزن)

```bash
git clone https://github.com/asanseir724/LinkHunterBot.git
cd LinkHunterBot
```

### 2. Install Dependencies (نصب وابستگی‌ها)

```bash
pip install -r dependencies.txt
```

### 3. Set Environment Variables (تنظیم متغیرهای محیطی)

Create a `.env` file in the project root with the following variables:
یک فایل `.env` در پوشه اصلی پروژه با متغیرهای زیر ایجاد کنید:

```
SESSION_SECRET=your_session_secret
TELEGRAM_BOT_TOKEN=your_telegram_bot_token

# For SMS notifications (optional) - برای اطلاع‌رسانی پیامکی (اختیاری)
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_PHONE_NUMBER=your_twilio_phone_number
```

### 4. Run the Application (اجرای برنامه)

```bash
python main.py
```

The web interface will be available at `http://localhost:5000`.
رابط وب در آدرس `http://localhost:5000` در دسترس خواهد بود.

## Configuration (پیکربندی)

1. **Bot Token**: Obtain a bot token from [@BotFather](https://t.me/BotFather) and set it in the `.env` file or through the settings page
2. **Add Channels**: Add Telegram channels to monitor through the web interface
3. **Configure Intervals**: Set how frequently the bot checks for new links
4. **SMS Notifications**: Optionally configure SMS notifications for when new links are found
5. **Category Keywords**: Configure keywords for automatic link categorization

1. **توکن ربات**: یک توکن ربات از [@BotFather](https://t.me/BotFather) دریافت کنید و آن را در فایل `.env` یا از طریق صفحه تنظیمات تنظیم کنید
2. **افزودن کانال‌ها**: کانال‌های تلگرام را برای نظارت از طریق رابط وب اضافه کنید
3. **پیکربندی فواصل زمانی**: تعیین کنید هر چند وقت یکبار ربات بررسی لینک‌های جدید را انجام دهد
4. **اطلاع‌رسانی پیامکی**: به صورت اختیاری اطلاع‌رسانی پیامکی را برای زمانی که لینک‌های جدید پیدا می‌شوند، پیکربندی کنید
5. **کلمات کلیدی دسته‌بندی**: کلمات کلیدی را برای دسته‌بندی خودکار لینک‌ها پیکربندی کنید

## User Accounts (Optional) - حساب‌های کاربری (اختیاری)

To monitor private channels, you can add user accounts:
برای نظارت بر کانال‌های خصوصی، می‌توانید حساب‌های کاربری اضافه کنید:

1. Go to the Accounts page
2. Add a new account with phone number, API ID, and API Hash
3. Complete the authorization process

1. به صفحه حساب‌ها بروید
2. یک حساب جدید با شماره تلفن، API ID و API Hash اضافه کنید
3. فرآیند احراز هویت را تکمیل کنید

## SMS Notifications (Optional) - اطلاع‌رسانی پیامکی (اختیاری)

To enable SMS notifications:
برای فعال کردن اطلاع‌رسانی پیامکی:

1. Create a Twilio account and get credentials
2. Set environment variables for Twilio
3. Configure notification settings in the web interface

1. یک حساب Twilio ایجاد کنید و اعتبارنامه‌ها را دریافت کنید
2. متغیرهای محیطی را برای Twilio تنظیم کنید
3. تنظیمات اطلاع‌رسانی را در رابط وب پیکربندی کنید

## Project Structure (ساختار پروژه)

- `main.py`: Main application entry point (نقطه ورود اصلی برنامه)
- `bot.py`: Telegram bot functionality (عملکرد ربات تلگرام)
- `link_manager.py`: Link extraction and management (استخراج و مدیریت لینک)
- `user_accounts.py`: User account integration (یکپارچه‌سازی حساب کاربری)
- `notification_utils.py`: Notification system (سیستم اطلاع‌رسانی)
- `templates/`: Web interface templates (قالب‌های رابط وب)
- `send_message.py`: SMS notification functionality (قابلیت اطلاع‌رسانی پیامکی)
- `web_scraper.py`: Web page scraping for links (استخراج لینک از صفحات وب)

## License (مجوز)

This project is open source software licensed under the MIT license.
این پروژه نرم‌افزار متن‌باز تحت مجوز MIT است.

## Support (پشتیبانی)

For questions, issues, or contributions, please open an issue on GitHub or contact the repository owner.
برای سوالات، مشکلات یا مشارکت‌ها، لطفا یک issue در گیت‌هاب باز کنید یا با مالک مخزن تماس بگیرید.