# راهنمای عیب‌یابی سیستم لینک‌یاب تلگرام

این راهنما به شما کمک می‌کند مشکلات رایج در راه‌اندازی و استفاده از سیستم لینک‌یاب تلگرام را برطرف کنید.

## عیب‌یابی سرویس linkdoni

اگر سرویس linkdoni با خطای `failed (Result: exit-code)` مواجه شد:

### 1. بررسی لاگ‌های خطا

```bash
sudo journalctl -u linkdoni.service -n 50
```

این دستور 50 خط آخر لاگ سرویس را نمایش می‌دهد و به شما کمک می‌کند دلیل دقیق خطا را پیدا کنید.

### 2. بهبود تنظیمات سرویس

فایل سرویس را ویرایش کنید:

```bash
sudo nano /etc/systemd/system/linkdoni.service
```

تنظیمات زیر را جایگزین محتوای فعلی کنید:

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

تغییرات اصلی:
- تغییر `Restart=always` به `Restart=on-failure` برای جلوگیری از تلاش‌های مکرر در صورت خطای مداوم
- اضافه کردن `RestartSec=10` برای فاصله 10 ثانیه‌ای بین راه‌اندازی‌های مجدد
- اضافه کردن محدودیت‌های راه‌اندازی مجدد با `StartLimitInterval` و `StartLimitBurst`

### 3. بررسی دسترسی‌های فایل‌ها

```bash
sudo chown -R www-data:www-data /var/www/linkdoni
sudo chmod -R 755 /var/www/linkdoni
sudo chmod -R 777 /var/www/linkdoni/logs /var/www/linkdoni/sessions /var/www/linkdoni/static/exports /var/www/linkdoni/php_version/sessions
```

### 4. بررسی محیط مجازی پایتون

```bash
cd /var/www/linkdoni
source venv/bin/activate
python -c "import sys; print(sys.executable)"
pip list | grep gunicorn
```

مطمئن شوید که gunicorn به درستی نصب شده است.

### 5. تست اجرای مستقیم

به جای استفاده از سرویس، برنامه را مستقیماً اجرا کنید تا خطاها را مشاهده کنید:

```bash
cd /var/www/linkdoni
source venv/bin/activate
gunicorn --bind 0.0.0.0:5000 --workers 1 main:app
```

اگر با خطایی مواجه شدید، آن را برطرف کنید و سپس دوباره سرویس را راه‌اندازی کنید.

### 6. راه‌اندازی مجدد سرویس

پس از انجام تغییرات، سرویس را مجدداً راه‌اندازی کنید:

```bash
sudo systemctl daemon-reload
sudo systemctl restart linkdoni.service
```

## رفع مشکلات تنظیمات محیطی

### 1. بررسی فایل .env

مطمئن شوید فایل .env به درستی پیکربندی شده و دارای تمام متغیرهای لازم است:

```bash
cat /var/www/linkdoni/.env
```

حداقل باید شامل:
- SESSION_SECRET
- TELEGRAM_BOT_TOKEN

### 2. بررسی نصب وابستگی‌ها

```bash
cd /var/www/linkdoni
source venv/bin/activate
pip list
```

در صورت نیاز، وابستگی‌ها را مجدداً نصب کنید:

```bash
pip install -r dependencies.txt
```

### 3. بررسی فضای دیسک

```bash
df -h
```

اطمینان حاصل کنید که فضای کافی برای فایل‌های لاگ وجود دارد.

## مشکلات خاص و راه حل‌های آنها

### مشکل: متغیر last_check_result یا lock تعریف نشده

اگر خطای مربوط به متغیرهای global در فایل main.py مشاهده کردید:

1. مطمئن شوید متغیر lock قبل از استفاده تعریف شده است:
```python
import threading
lock = threading.Lock()

# سایر متغیرهای global
last_check_result = {
    'timestamp': None,
    'status': 'not_run',
    'new_links': 0,
    # سایر فیلدها...
}
```

2. در توابعی که از این متغیرهای global استفاده می‌کنند، حتماً آنها را با کلیدواژه global در ابتدای تابع تعریف کنید:
```python
def some_function():
    global lock, last_check_result
    with lock:
        # عملیات روی متغیر last_check_result
```

### مشکل: تابع check_websites_for_links یافت نشد

اگر خطای ImportError یا AttributeError مربوط به تابع check_websites_for_links دریافت کردید:

1. مطمئن شوید این تابع در فایل main.py تعریف شده و در همان فایل استفاده می‌شود
2. اگر از فایل دیگری import می‌شود، مطمئن شوید مسیر import صحیح است

### مشکل: خطای دسترسی به فایل‌های لاگ

اگر با خطای دسترسی به فایل‌های لاگ مواجه شدید:

```bash
# ایجاد پوشه لاگ اگر وجود ندارد
sudo mkdir -p /var/www/linkdoni/logs

# تنظیم مجوزهای مناسب
sudo chown -R www-data:www-data /var/www/linkdoni/logs
sudo chmod 777 /var/www/linkdoni/logs
```

## سایر مشکلات رایج

### خطا: No module named 'X'

راه حل: وابستگی مورد نیاز را نصب کنید:

```bash
source venv/bin/activate
pip install X
```

### خطا: Permission denied

راه حل: دسترسی‌های فایل را بررسی و اصلاح کنید:

```bash
sudo chown -R www-data:www-data /var/www/linkdoni
```

### خطا: Address already in use

راه حل: بررسی کنید کدام فرآیند پورت 5000 را اشغال کرده و آن را متوقف کنید:

```bash
sudo lsof -i :5000
sudo kill -9 [PID]
```

### خطا: نمی‌توان به API تلگرام متصل شد

راه حل: بررسی کنید توکن ربات معتبر است و دسترسی به سرورهای تلگرام وجود دارد.

## تماس با پشتیبانی

اگر پس از انجام تمامی مراحل عیب‌یابی، همچنان با مشکل مواجه هستید، اطلاعات زیر را جمع‌آوری کرده و به آدرس پشتیبانی ارسال کنید:

1. خروجی کامل دستور `journalctl -u linkdoni.service`
2. محتوای فایل‌های لاگ در پوشه `/var/www/linkdoni/logs`
3. نسخه سیستم عامل با دستور `lsb_release -a`
4. نسخه پایتون با دستور `python --version`
5. لیست پکیج‌های نصب شده با دستور `pip list`