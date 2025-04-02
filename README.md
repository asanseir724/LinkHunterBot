# Link Hunter Bot (ربات لینک‌یاب تلگرام)

A comprehensive Telegram bot designed to automatically extract and store links from Telegram channels. The bot monitors specified channels, extracts links from messages, and provides a web interface for managing the collected links.

ربات قدرتمند تلگرام برای استخراج و ذخیره خودکار لینک‌ها از کانال‌های تلگرام. این ربات کانال‌های مشخص شده را بررسی کرده، لینک‌ها را از پیام‌ها استخراج می‌کند و یک رابط وب برای مدیریت لینک‌های جمع‌آوری شده ارائه می‌دهد.

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

## One-Step Installation (نصب یک مرحله‌ای)

For quick deployment on a server, you can use our one-step installation script:
برای استقرار سریع روی سرور، می‌توانید از اسکریپت نصب یک مرحله‌ای ما استفاده کنید:

```bash
# Download the installation script
wget https://raw.githubusercontent.com/asanseir724/LinkHunterBot/main/install.sh

# Make it executable
chmod +x install.sh

# Run with sudo
sudo ./install.sh
```

This script will:
این اسکریپت:

- Update your system and install prerequisites
- Clone the repository
- Set up a Python virtual environment
- Install all required dependencies
- Configure environment variables
- Create necessary directories
- Set up a systemd service for automatic startup
- Start the application

- سیستم شما را به‌روز کرده و پیش‌نیازها را نصب می‌کند
- مخزن را کلون می‌کند
- یک محیط مجازی پایتون راه‌اندازی می‌کند
- تمام وابستگی‌های مورد نیاز را نصب می‌کند
- متغیرهای محیطی را پیکربندی می‌کند
- دایرکتوری‌های لازم را ایجاد می‌کند
- یک سرویس systemd برای راه‌اندازی خودکار تنظیم می‌کند
- برنامه را اجرا می‌کند

After installation, the web interface will be accessible at `http://YOUR_SERVER_IP:5000`.
پس از نصب، رابط وب در آدرس `http://IP_سرور_شما:5000` قابل دسترسی خواهد بود.

## Server Deployment Guide (راهنمای استقرار روی سرور)

### System Requirements (پیش‌نیازهای سیستم)

Recommended server specifications:
مشخصات پیشنهادی سرور:

- OS: Ubuntu 20.04 or higher (سیستم عامل: اوبونتو ۲۰.۰۴ یا بالاتر)
- RAM: 2GB minimum, 4GB recommended (حداقل ۲ گیگابایت، ۴ گیگابایت پیشنهاد می‌شود)
- Disk Space: 10GB minimum (حداقل ۱۰ گیگابایت فضای دیسک)
- CPU: 1 core minimum, 2 cores recommended (حداقل ۱ هسته، ۲ هسته پیشنهاد می‌شود)

### Full Installation Process (فرآیند کامل نصب)

If you prefer to install manually instead of using the one-step script, follow these steps:
اگر ترجیح می‌دهید به جای استفاده از اسکریپت یک مرحله‌ای، به صورت دستی نصب کنید، این مراحل را دنبال کنید:

#### 1. Update System and Install Prerequisites (به‌روزرسانی سیستم و نصب پیش‌نیازها)

```bash
# Update system
sudo apt update
sudo apt upgrade -y
# Install essential prerequisites
sudo apt install -y python3 python3-pip python3-venv git screen build-essential libssl-dev
```

#### 2. Install PostgreSQL (Optional) (نصب PostgreSQL - اختیاری)

```bash
# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib
# Create user and database
sudo -u postgres psql -c "CREATE USER linkdoni WITH PASSWORD 'your_password';"
sudo -u postgres psql -c "CREATE DATABASE linkdoni_db OWNER linkdoni;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE linkdoni_db TO linkdoni;"
```

#### 3. Download and Prepare Project (دانلود و آماده‌سازی پروژه)

```bash
# Create directory for project
mkdir -p /var/www/linkdoni
cd /var/www/linkdoni
# Clone repository
git clone https://github.com/asanseir724/LinkHunterBot.git .
# Create Python virtual environment
python3 -m venv venv
source venv/bin/activate
# Install required libraries
pip install -r dependencies.txt
```

#### 4. Create Required Directories (ایجاد پوشه‌های مورد نیاز)

```bash
# Create directories for logs and exports
mkdir -p logs
mkdir -p static/exports
mkdir -p sessions
chmod 777 logs static/exports sessions
```

#### 5. Configure Environment Variables (تنظیم متغیرهای محیطی)

```bash
# Create environment file
cat > .env << EOL
# Project environment variables
SESSION_SECRET=a_long_random_string
TELEGRAM_BOT_TOKEN=your_telegram_bot_token

# Twilio settings (optional for SMS notifications)
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_PHONE_NUMBER=your_twilio_phone_number

# Database settings (if using PostgreSQL)
DATABASE_URL=postgresql://linkdoni:your_password@localhost/linkdoni_db
EOL

# Apply environment variables
set -a
source .env
set +a
```

#### 6. Initial Application Test (تست اولیه برنامه)

```bash
# Test running the application
python main.py
# If successful, stop it with Ctrl+C
```

#### 7. Set up Gunicorn Service (راه‌اندازی سرویس Gunicorn)

```bash
# Create Gunicorn service file
sudo tee /etc/systemd/system/linkdoni.service > /dev/null <<EOL
[Unit]
Description=Telegram Link Hunter Bot
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/linkdoni
Environment="PATH=/var/www/linkdoni/venv/bin"
EnvironmentFile=/var/www/linkdoni/.env
ExecStart=/var/www/linkdoni/venv/bin/gunicorn --workers 3 --bind 0.0.0.0:5000 --access-logfile /var/www/linkdoni/logs/access.log --error-logfile /var/www/linkdoni/logs/error.log main:app
Restart=always

[Install]
WantedBy=multi-user.target
EOL

# Change ownership of directory
sudo chown -R www-data:www-data /var/www/linkdoni

# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable linkdoni
sudo systemctl start linkdoni
```

#### 8. Install Nginx (Optional) (نصب Nginx - اختیاری)

```bash
# Install Nginx
sudo apt install -y nginx

# Configure Nginx
sudo tee /etc/nginx/sites-available/linkdoni > /dev/null <<EOL
server {
    listen 80;
    server_name your_server_domain_or_IP;
    
    location / {
        include proxy_params;
        proxy_pass http://127.0.0.1:5000;
    }
}
EOL

# Enable site
sudo ln -s /etc/nginx/sites-available/linkdoni /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx

# Install Let's Encrypt for SSL (optional)
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your_domain
```

### Useful Commands (دستورات مفید)

```bash
# Check service status
sudo systemctl status linkdoni

# Restart service
sudo systemctl restart linkdoni

# View system logs
sudo journalctl -u linkdoni.service -f

# View application logs
tail -f /var/www/linkdoni/logs/bot.log

# Update application from repository
cd /var/www/linkdoni
git pull
source venv/bin/activate
pip install -r dependencies.txt
sudo systemctl restart linkdoni

# Run application in background with Screen (alternative method)
cd /var/www/linkdoni
screen -S linkdoni
source venv/bin/activate
source .env
python main.py
# To detach from screen: Ctrl+A then D
# To reattach to screen: screen -r linkdoni
```

### Troubleshooting (عیب‌یابی)

If the application doesn't run:
اگر برنامه اجرا نمی‌شود:

```bash
# Check logs for errors
tail -f /var/www/linkdoni/logs/bot.log
sudo journalctl -u linkdoni.service -f

# Check permissions
sudo chown -R www-data:www-data /var/www/linkdoni
sudo chmod -R 755 /var/www/linkdoni
sudo chmod -R 777 /var/www/linkdoni/logs /var/www/linkdoni/static/exports /var/www/linkdoni/sessions
```

If you can't access the website:
اگر به وب‌سایت دسترسی ندارید:

```bash
# Check Nginx status
sudo systemctl status nginx

# Check firewall
sudo ufw status
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

## Configuration (پیکربندی)

1. **Bot Token**: Obtain a bot token from [@BotFather](https://t.me/BotFather) and set it in the `.env` file or through the settings page
2. **Add Channels**: Add Telegram channels to monitor through the web interface
3. **Add Websites**: Add websites to crawl for Telegram links through the web interface
4. **Configure Intervals**: Set how frequently the bot checks for new links
5. **SMS Notifications**: Optionally configure SMS notifications for when new links are found
6. **Category Keywords**: Configure keywords for automatic link categorization
7. **Website Scrolling**: Configure how many times to scroll each website during crawling

1. **توکن ربات**: یک توکن ربات از [@BotFather](https://t.me/BotFather) دریافت کنید و آن را در فایل `.env` یا از طریق صفحه تنظیمات تنظیم کنید
2. **افزودن کانال‌ها**: کانال‌های تلگرام را برای نظارت از طریق رابط وب اضافه کنید
3. **افزودن وب‌سایت‌ها**: وب‌سایت‌هایی را برای جمع‌آوری لینک‌های تلگرام از طریق رابط وب اضافه کنید
4. **پیکربندی فواصل زمانی**: تعیین کنید هر چند وقت یکبار ربات بررسی لینک‌های جدید را انجام دهد
5. **اطلاع‌رسانی پیامکی**: به صورت اختیاری اطلاع‌رسانی پیامکی را برای زمانی که لینک‌های جدید پیدا می‌شوند، پیکربندی کنید
6. **کلمات کلیدی دسته‌بندی**: کلمات کلیدی را برای دسته‌بندی خودکار لینک‌ها پیکربندی کنید
7. **اسکرول وب‌سایت‌ها**: تعیین کنید هر وب‌سایت چند بار اسکرول شود در طول خزش

## User Accounts (Optional) - حساب‌های کاربری (اختیاری)

To monitor private channels, you can add user accounts:
برای نظارت بر کانال‌های خصوصی، می‌توانید حساب‌های کاربری اضافه کنید:

1. Go to the Accounts page
2. Add a new account with phone number, API ID, and API Hash
3. Complete the authorization process

1. به صفحه حساب‌ها بروید
2. یک حساب جدید با شماره تلفن، API ID و API Hash اضافه کنید
3. فرآیند احراز هویت را تکمیل کنید

## Website Crawling for Links (خزش وب‌سایت برای لینک‌ها)

The bot can extract Telegram links from websites automatically:
ربات می‌تواند به طور خودکار لینک‌های تلگرام را از وب‌سایت‌ها استخراج کند:

1. Go to the Websites page in the interface
2. Add website URLs to monitor (one per line)
3. Set the scroll count for dynamic websites
4. Configure check interval to determine how often to crawl websites
5. Categorize websites for better link organization

1. به صفحه وب‌سایت‌ها در رابط کاربری بروید
2. آدرس وب‌سایت‌هایی را که می‌خواهید نظارت کنید اضافه کنید (هر خط یک آدرس)
3. تعداد اسکرول را برای وب‌سایت‌های پویا تنظیم کنید
4. فاصله زمانی بررسی را تنظیم کنید تا مشخص شود هر چند وقت یکبار وب‌سایت‌ها بررسی شوند
5. وب‌سایت‌ها را برای سازماندهی بهتر لینک‌ها دسته‌بندی کنید

### Supported Link Formats (فرمت‌های لینک پشتیبانی شده)

The crawler can detect and extract:
خزنده می‌تواند این موارد را تشخیص داده و استخراج کند:

- Regular t.me links (https://t.me/channel_name)
- Private group links (https://t.me/joinchat/XXXXX)
- Plus-format private links (https://t.me/+XXXXX)
- Username format (@channel_name)
- Old format links (https://telegram.me/channel_name)

- لینک‌های معمولی t.me (https://t.me/channel_name)
- لینک‌های گروه خصوصی (https://t.me/joinchat/XXXXX)
- لینک‌های خصوصی با فرمت جدید (https://t.me/+XXXXX)
- فرمت نام کاربری (@channel_name)
- لینک‌های با فرمت قدیمی (https://telegram.me/channel_name)

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
- `web_crawler.py`: Web crawling for Telegram links with auto-scrolling (خزش وب برای لینک‌های تلگرام با اسکرول خودکار)
- `web_scraper.py`: Static web page scraping for links (استخراج لینک از صفحات وب ثابت)
- `async_helper.py`: Centralized event loop management (مدیریت متمرکز حلقه رویداد)
- `account_routes.py`: Routes for Telegram user account management (مسیرهای مدیریت حساب کاربری تلگرام)
- `logger.py`: Centralized logging functionality (قابلیت لاگینگ متمرکز)

## License (مجوز)

This project is open source software licensed under the MIT license.
این پروژه نرم‌افزار متن‌باز تحت مجوز MIT است.

## Support (پشتیبانی)

For questions, issues, or contributions, please open an issue on GitHub or contact the repository owner.
برای سوالات، مشکلات یا مشارکت‌ها، لطفا یک issue در گیت‌هاب باز کنید یا با مالک مخزن تماس بگیرید.