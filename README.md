# Link Hunter Bot (ربات لینک‌یاب تلگرام)

A comprehensive Telegram bot designed to automatically extract and store links from Telegram channels, respond to private messages with AI, and provide advanced management features. The system monitors specified channels, extracts links from messages, responds intelligently to user inquiries, and provides a full-featured web interface for managing all aspects of the bot.

ربات قدرتمند تلگرام برای استخراج و ذخیره خودکار لینک‌ها از کانال‌های تلگرام، پاسخگویی به پیام‌های خصوصی با هوش مصنوعی و ارائه ویژگی‌های مدیریتی پیشرفته. این سیستم کانال‌های مشخص شده را بررسی می‌کند، لینک‌ها را از پیام‌ها استخراج می‌کند، به پرسش‌های کاربران به صورت هوشمند پاسخ می‌دهد و یک رابط وب کامل برای مدیریت تمام جنبه‌های ربات ارائه می‌دهد.

## Features (ویژگی‌ها)

- **Automated Link Extraction**: Monitors Telegram channels and extracts links automatically
- **Web Crawling**: Extracts Telegram links from websites with support for multiple formats
- **Category-based Link Organization**: Classifies links into categories based on channel or content
- **Web Dashboard**: Full-featured web interface for managing channels, websites, links, and settings
- **Excel Export**: Export links to Excel format with timestamps
- **Token Rotation**: Support for multiple Telegram Bot tokens to avoid rate limits
- **User Account Integration**: Support for adding Telegram user accounts to access private channels
- **SMS Notifications**: Optional SMS notifications via Twilio when new links are found
- **Auto-discovery**: Automatically finds and adds new link-sharing channels
- **Keyword Management**: Manage category keywords for automatic link classification
- **Support for @ format links**: Detects both t.me links and @username format
- **AI-Powered Responses**: Automatically respond to private messages with AI (Avalai.ir & Perplexity)
- **Telegram Desktop Interface**: View and manage private messages in a Telegram Desktop-style interface
- **Dual AI Service Support**: Switch between different AI services (Avalai.ir and Perplexity)
- **Customizable AI Prompts**: Configure how the AI responds to different types of messages
- **Message History**: Track and review all private message conversations
- **Batch Account Management**: Connect all Telegram accounts at once with a single click
- **Enhanced Error Handling**: Robust error recovery and reconnection capabilities
- **Web Scraping**: Extract content from websites for AI processing
- **Multi-language Support**: Full support for Persian and English interfaces

- **استخراج خودکار لینک**: نظارت بر کانال‌های تلگرام و استخراج خودکار لینک‌ها
- **خزش وب**: استخراج لینک‌های تلگرام از وب‌سایت‌ها با پشتیبانی از فرمت‌های مختلف
- **دسته‌بندی لینک‌ها**: طبقه‌بندی لینک‌ها در دسته‌های مختلف بر اساس کانال یا محتوا
- **داشبورد وب**: رابط کاربری وب کامل برای مدیریت کانال‌ها، وب‌سایت‌ها، لینک‌ها و تنظیمات
- **صدور اکسل**: صدور لینک‌ها به فرمت اکسل همراه با برچسب زمانی
- **چرخش توکن**: پشتیبانی از چندین توکن ربات تلگرام برای جلوگیری از محدودیت‌های نرخ
- **یکپارچه‌سازی حساب کاربری**: پشتیبانی از افزودن حساب‌های کاربری تلگرام برای دسترسی به کانال‌های خصوصی
- **اطلاع‌رسانی پیامکی**: اطلاع‌رسانی پیامکی اختیاری از طریق Twilio هنگام یافتن لینک‌های جدید
- **کشف خودکار**: یافتن و افزودن خودکار کانال‌های اشتراک‌گذاری لینک جدید
- **مدیریت کلمات کلیدی**: مدیریت کلمات کلیدی دسته‌بندی‌ها برای طبقه‌بندی خودکار لینک‌ها
- **پشتیبانی از لینک‌های فرمت @**: تشخیص هر دو فرمت لینک t.me و فرمت @username
- **پاسخ‌های هوشمند با هوش مصنوعی**: پاسخگویی خودکار به پیام‌های خصوصی با هوش مصنوعی (آوالای و پرپلکسیتی)
- **رابط کاربری شبیه تلگرام دسکتاپ**: مشاهده و مدیریت پیام‌های خصوصی در یک رابط شبیه به تلگرام دسکتاپ
- **پشتیبانی از دو سرویس هوش مصنوعی**: امکان تغییر بین سرویس‌های مختلف هوش مصنوعی (آوالای و پرپلکسیتی)
- **پرامپت‌های قابل تنظیم هوش مصنوعی**: تنظیم نحوه پاسخگویی هوش مصنوعی به انواع مختلف پیام‌ها
- **تاریخچه پیام‌ها**: پیگیری و بررسی تمام مکالمات پیام خصوصی
- **مدیریت دسته‌ای اکانت‌ها**: اتصال همه اکانت‌های تلگرام با یک کلیک
- **مدیریت خطای پیشرفته**: قابلیت‌های قوی بازیابی خطا و اتصال مجدد
- **استخراج محتوا از وب**: استخراج محتوا از وب‌سایت‌ها برای پردازش توسط هوش مصنوعی
- **پشتیبانی چندزبانه**: پشتیبانی کامل از رابط کاربری فارسی و انگلیسی

## Requirements (پیش‌نیازها)

- Python 3.8+
- Flask
- Telethon
- Twilio (optional, for SMS notifications)
- PostgreSQL (optional, for larger deployments)
- API Keys for AI services:
  - Avalai.ir API key (optional, for Avalai AI integration)
  - Perplexity API key (optional, for Perplexity AI integration)

## AI Message Response System (سیستم پاسخگویی هوش مصنوعی)

The bot can automatically respond to private messages using AI:
ربات می‌تواند با استفاده از هوش مصنوعی به پیام‌های خصوصی به طور خودکار پاسخ دهد:

### AI Service Options (گزینه‌های سرویس هوش مصنوعی)

1. **Avalai.ir**: Persian-focused AI service
2. **Perplexity**: Advanced AI service with multiple model options

1. **آوالای**: سرویس هوش مصنوعی با تمرکز بر زبان فارسی
2. **پرپلکسیتی**: سرویس هوش مصنوعی پیشرفته با گزینه‌های مختلف مدل

### Configuration (پیکربندی)

1. Add your API keys to the environment variables
2. Go to the Private Messages page in the web interface
3. Enable the desired AI service
4. Configure the default prompt and other settings
5. The system will automatically respond to incoming messages based on your settings

1. کلیدهای API خود را به متغیرهای محیطی اضافه کنید
2. به صفحه پیام‌های خصوصی در رابط وب بروید
3. سرویس هوش مصنوعی مورد نظر را فعال کنید
4. پرامپت پیش‌فرض و سایر تنظیمات را پیکربندی کنید
5. سیستم بر اساس تنظیمات شما به طور خودکار به پیام‌های ورودی پاسخ می‌دهد

### Perplexity API Configuration (پیکربندی API پرپلکسیتی)

The Perplexity integration supports advanced settings:
یکپارچه‌سازی پرپلکسیتی از تنظیمات پیشرفته پشتیبانی می‌کند:

- Model selection (llama-3.1-sonar-small-128k-online recommended)
- Temperature control
- Maximum token limit
- Response filtering options
- Question detection (option to only respond to questions)

- انتخاب مدل (llama-3.1-sonar-small-128k-online توصیه می‌شود)
- کنترل Temperature
- محدودیت حداکثر توکن
- گزینه‌های فیلتر پاسخ
- تشخیص سؤال (گزینه پاسخ فقط به سؤالات)

## User Accounts for Private Access (حساب‌های کاربری برای دسترسی خصوصی)

To monitor private channels, you can add user accounts:
برای نظارت بر کانال‌های خصوصی، می‌توانید حساب‌های کاربری اضافه کنید:

### Adding a New Account (افزودن حساب جدید)

1. Go to the Accounts page
2. Click "Add Account"
3. Enter the phone number with international format
4. Enter API ID and API Hash from my.telegram.org/apps
5. Complete the verification process
6. Add optional nickname for easy identification

1. به صفحه حساب‌ها بروید
2. روی "افزودن اکانت" کلیک کنید
3. شماره تلفن را با فرمت بین‌المللی وارد کنید
4. API ID و API Hash را از my.telegram.org/apps وارد کنید
5. فرآیند تأیید را تکمیل کنید
6. نام مستعار اختیاری برای شناسایی آسان اضافه کنید

### Batch Management (مدیریت دسته‌ای)

- Use "Connect All Accounts" button to reconnect all accounts at once
- This is useful for recovering from connection errors
- The system automatically manages connection sessions and retries

- از دکمه "اتصال همه اکانت‌ها" برای اتصال مجدد همه اکانت‌ها به یکباره استفاده کنید
- این برای بازیابی از خطاهای اتصال مفید است
- سیستم به طور خودکار جلسات اتصال و تلاش‌های مجدد را مدیریت می‌کند

## Project Structure (ساختار پروژه)

- `main.py`: Main application entry point (نقطه ورود اصلی برنامه)
- `bot.py`: Telegram bot functionality (عملکرد ربات تلگرام)
- `link_manager.py`: Link extraction and management (استخراج و مدیریت لینک)
- `user_accounts.py`: User account integration (یکپارچه‌سازی حساب کاربری)
- `notification_utils.py`: Notification system (سیستم اطلاع‌رسانی)
- `templates/`: Web interface templates (قالب‌های رابط وب)
- `send_message.py`: SMS notification functionality (قابلیت اطلاع‌رسانی پیامکی)
- `web_crawler.py`: Web crawling for Telegram links with auto-scrolling (خزش وب برای لینک‌های تلگرام با اسکرول خودکار)
- `web_scraper.py`: Static web page scraping for links (استخراج لینک از صفحات وب ثابت)
- `async_helper.py`: Centralized event loop management (مدیریت متمرکز حلقه رویداد)
- `account_routes.py`: Routes for Telegram user account management (مسیرهای مدیریت حساب کاربری تلگرام)
- `logger.py`: Centralized logging functionality (قابلیت لاگینگ متمرکز)
- `avalai_api.py`: Integration with Avalai.ir AI service (یکپارچه‌سازی با سرویس هوش مصنوعی آوالای)
- `perplexity_api.py`: Integration with Perplexity AI service (یکپارچه‌سازی با سرویس هوش مصنوعی پرپلکسیتی)
- `scheduler.py`: Scheduled task management (مدیریت وظایف زمان‌بندی شده)

## License (مجوز)

This project is open source software licensed under the MIT license.
این پروژه نرم‌افزار متن‌باز تحت مجوز MIT است.

## Support (پشتیبانی)

For questions, issues, or contributions, please open an issue on GitHub or contact the repository owner.
برای سوالات، مشکلات یا مشارکت‌ها، لطفا یک issue در گیت‌هاب باز کنید یا با مالک مخزن تماس بگیرید.
