# سیستم جمع‌آوری لینک‌های تلگرام (Link Hunter Bot)

سیستم هوشمند استخراج و ذخیره‌سازی لینک‌های کانال‌ها و گروه‌های تلگرام با قابلیت مدیریت حساب‌های کاربری، استخراج لینک از وب‌سایت‌ها و پاسخگویی هوشمند به پیام‌های خصوصی.

## ویژگی‌ها

✅ **جمع‌آوری خودکار لینک‌ها**: استخراج اتوماتیک لینک از کانال‌ها و گروه‌های تلگرام

✅ **مدیریت حساب‌های کاربری تلگرام**: پشتیبانی از چندین حساب کاربری با احراز هویت دو مرحله‌ای

✅ **اتصال دسته‌ای حساب‌ها**: قابلیت اتصال همزمان همه حساب‌های کاربری با یک کلیک

✅ **استخراج لینک از وب‌سایت‌ها**: جمع‌آوری لینک‌های تلگرام از وب‌سایت‌های مختلف

✅ **پاسخگویی هوشمند**: پاسخ خودکار به پیام‌های خصوصی با استفاده از هوش مصنوعی Avalai.ir و Perplexity

✅ **ارسال نوتیفیکیشن SMS**: اطلاع‌رسانی لینک‌های جدید از طریق پیامک با سرویس Twilio

✅ **رابط کاربری شبیه تلگرام دسکتاپ**: مشاهده و مدیریت پیام‌های خصوصی با رابط کاربری مشابه تلگرام

✅ **صادرات اطلاعات**: خروجی Excel از لینک‌های جمع‌آوری شده و آمار

✅ **پشتیبانی از GitLab و GitHub**: امکان کلون کردن کد از هر دو مخزن

✅ **پشتیبانی از دو زبان**: رابط کاربری به دو زبان فارسی و انگلیسی

## نیازمندی‌ها

- سیستم عامل: Linux (Ubuntu/Debian)
- Python 3.8+
- PHP 8.0+ (برای MadelineProto و بخش PHP)
- دسترسی ادمین (root) برای نصب سرویس
- توکن ربات تلگرام

## نصب سریع

### نصب با یک کد

برای نصب سریع و یک مرحله‌ای سیستم لینک‌یاب تلگرام، دستور زیر را در ترمینال لینوکس اجرا کنید:

```bash
wget -O install.sh https://raw.githubusercontent.com/asanseir724/LinkHunterBot/main/install.sh && chmod +x install.sh && sudo ./install.sh
```

این دستور به صورت خودکار:
1. فایل اسکریپت نصب را دانلود می‌کند
2. به آن دسترسی اجرا می‌دهد
3. اسکریپت را با دسترسی روت اجرا می‌کند

سپس اسکریپت نصب، تمام مراحل زیر را به صورت خودکار انجام می‌دهد:
- به‌روزرسانی سیستم و نصب پیش‌نیازها
- دانلود کد از مخزن GitHub یا GitLab (با انتخاب شما)
- نصب PHP 8.2 و Composer برای پشتیبانی از MadelineProto
- راه‌اندازی محیط مجازی پایتون و نصب کتابخانه‌ها
- دریافت اطلاعات لازم مانند توکن ربات و API های مختلف
- نصب و راه‌اندازی به عنوان سرویس سیستمی

### روش 2: نصب دستی

1. ابتدا مخزن را کلون کنید:

```bash
# از GitHub
git clone https://github.com/asanseir724/LinkHunterBot.git

# یا از GitLab
git clone https://gitlab.com/your-username/LinkHunterBot.git
```

2. وارد پوشه پروژه شوید:

```bash
cd LinkHunterBot
```

3. محیط مجازی پایتون را ایجاد کنید:

```bash
python3 -m venv venv
source venv/bin/activate
```

4. وابستگی‌ها را نصب کنید:

```bash
pip install --upgrade pip
pip install -r dependencies.txt
```

5. پوشه‌های مورد نیاز را ایجاد کنید:

```bash
mkdir -p logs
mkdir -p static/exports
mkdir -p sessions
mkdir -p php_version/sessions
chmod 777 logs static/exports sessions php_version/sessions
```

6. برای پشتیبانی از MadelineProto، PHP 8.2 و Composer را نصب کنید:

```bash
# نصب PHP 8.2
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.2 php8.2-cli php8.2-common php8.2-curl php8.2-gd php8.2-mbstring php8.2-mysql php8.2-xml php8.2-zip php8.2-bcmath

# نصب Composer
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

7. فایل متغیرهای محیطی را ایجاد کنید:

```bash
cat > .env << EOL
# متغیرهای محیطی پروژه
SESSION_SECRET=$(openssl rand -hex 32)
TELEGRAM_BOT_TOKEN=YOUR_BOT_TOKEN
DATABASE_URL=sqlite:///links.db

# تنظیمات Twilio (برای ارسال نوتیفیکیشن SMS)
TWILIO_ACCOUNT_SID=YOUR_TWILIO_SID
TWILIO_AUTH_TOKEN=YOUR_TWILIO_TOKEN
TWILIO_PHONE_NUMBER=YOUR_TWILIO_PHONE

# تنظیمات هوش مصنوعی (برای پاسخگویی خودکار به پیام‌های خصوصی)
AVALAI_API_KEY=YOUR_AVALAI_API_KEY
PERPLEXITY_API_KEY=YOUR_PERPLEXITY_API_KEY
EOL
```

8. برنامه را اجرا کنید:

```bash
gunicorn --bind 0.0.0.0:5000 --workers 3 main:app
```

## راه‌اندازی به عنوان سرویس

برای اجرای برنامه به صورت سرویس در پس‌زمینه، فایل سرویس زیر را ایجاد کنید:

```bash
cat > /etc/systemd/system/linkdoni.service << EOL
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

# راه‌اندازی سرویس
systemctl daemon-reload
systemctl enable linkdoni
systemctl start linkdoni
```

## راهنمای استفاده

### تنظیم کانال‌های منبع

1. به صفحه "منابع" (Sources) بروید
2. نام کانال یا گروه مورد نظر را در فرمت `@channel_username` وارد کنید
3. روی دکمه "افزودن" کلیک کنید

### مدیریت حساب‌های کاربری تلگرام

1. به صفحه "حساب‌های کاربری" (Accounts) بروید
2. روی دکمه "افزودن حساب" کلیک کنید
3. شماره تلفن را با فرمت بین‌المللی وارد کنید
4. کد تایید را که به تلفن ارسال می‌شود وارد کنید
5. در صورت نیاز، رمز عبور دو مرحله‌ای را وارد کنید

### اتصال دسته‌ای حساب‌ها

1. به صفحه "حساب‌های کاربری" (Accounts) بروید
2. روی دکمه "اتصال همه حساب‌ها" کلیک کنید
3. سیستم به صورت خودکار تمام حساب‌های در وضعیت غیرمتصل را متصل می‌کند

### تنظیم پاسخگویی هوشمند

1. به صفحه "پیام‌های خصوصی" (Private Messages) بروید
2. در بخش تنظیمات AI، منبع هوش مصنوعی را انتخاب کنید (Avalai.ir یا Perplexity)
3. پرامپت مورد نظر برای پاسخگویی را تنظیم کنید
4. عملکرد آن را با بخش پیش‌نمایش بررسی کنید

### مشاهده پیام‌های خصوصی

1. به صفحه "پیام‌های خصوصی" (Private Messages) بروید
2. برای مشاهده همه پیام‌ها، به صورت پیش‌فرض پیام‌های همه حساب‌ها نمایش داده می‌شود
3. برای مشاهده رابط کاربری شبیه تلگرام دسکتاپ، روی دکمه "Telegram Desktop Style" کلیک کنید

### استخراج لینک از وب‌سایت‌ها

1. به صفحه "وب‌سایت‌ها" (Websites) بروید
2. آدرس وب‌سایت مورد نظر را وارد کنید
3. فاصله زمانی بررسی را تنظیم کنید
4. روی دکمه "افزودن" کلیک کنید

## میزبانی در GitLab

برای استفاده از GitLab به عنوان مخزن، مراحل زیر را دنبال کنید:

1. یک پروژه جدید در GitLab ایجاد کنید
2. مخزن GitHub را به GitLab متصل کنید:

```bash
# افزودن remote برای GitLab
git remote add gitlab https://gitlab.com/your-username/LinkHunterBot.git

# ارسال کد به GitLab
git push -u gitlab main
```

3. برای اطلاعات بیشتر به فایل `gitlab_setup.md` مراجعه کنید

## عیب‌یابی رایج

### خطای دسترسی به پوشه‌ها

اگر با خطای دسترسی به پوشه‌های `logs`، `sessions` یا `static/exports` مواجه شدید:

```bash
chmod -R 777 logs sessions static/exports php_version/sessions
```

### مشکل اتصال به API تلگرام

اگر با خطای ارتباط با API تلگرام مواجه شدید، مطمئن شوید:

1. توکن ربات معتبر است
2. دسترسی به سرورهای تلگرام وجود دارد
3. تنظیمات پراکسی در صورت نیاز انجام شده است

### مشکل اتصال حساب‌های کاربری

اگر در اتصال حساب‌ها مشکل دارید:

1. از صحت شماره وارد شده اطمینان حاصل کنید
2. محدودیت‌های IP را بررسی کنید (ممکن است نیاز به استفاده از پراکسی باشد)
3. کد تایید و رمز دو مرحله‌ای را با دقت وارد کنید

### خطای سرویس linkdoni

اگر سرویس linkdoni با خطای `failed (Result: exit-code)` مواجه شد:

1. بررسی لاگ‌های خطا:
   ```bash
   sudo journalctl -u linkdoni.service -n 50
   ```

2. بررسی و بهبود تنظیمات سرویس:
   ```bash
   sudo nano /etc/systemd/system/linkdoni.service
   ```
   
   تغییرات پیشنهادی برای فایل سرویس:
   ```
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
   Restart=on-failure
   RestartSec=10
   StartLimitInterval=60
   StartLimitBurst=3

   [Install]
   WantedBy=multi-user.target
   ```

3. بررسی دسترسی‌های فایل‌ها:
   ```bash
   sudo chown -R www-data:www-data /var/www/linkdoni
   sudo chmod -R 755 /var/www/linkdoni
   sudo chmod -R 777 /var/www/linkdoni/logs /var/www/linkdoni/sessions /var/www/linkdoni/static/exports /var/www/linkdoni/php_version/sessions
   ```

4. راه‌اندازی مجدد سرویس:
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl restart linkdoni.service
   ```

## توسعه و مشارکت

برای مشارکت در توسعه پروژه:

1. یک نسخه از مخزن را fork کنید
2. تغییرات خود را اعمال کنید
3. یک Pull Request ایجاد کنید

## مجوز

این پروژه تحت مجوز MIT منتشر شده است.