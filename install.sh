#!/bin/bash
# نصب یک مرحله‌ای سیستم لینک‌یاب تلگرام
# One-Step Installation Script for Telegram Link Hunter Bot

# رنگ‌های مورد استفاده در خروجی
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # بدون رنگ

# نمایش پیام خوش‌آمدگویی
echo -e "${BLUE}==================================================${NC}"
echo -e "${GREEN}      نصب یک مرحله‌ای سیستم لینک‌یاب تلگرام${NC}"
echo -e "${GREEN}      One-Step Link Hunter Bot Installation${NC}"
echo -e "${BLUE}==================================================${NC}"
echo ""

# بررسی اجرا با دسترسی root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}لطفاً این اسکریپت را با دسترسی root اجرا کنید:${NC}"
  echo -e "${YELLOW}sudo bash install.sh${NC}"
  exit 1
fi

# بررسی نصب بودن دیپندنسی‌های اولیه
echo -e "${BLUE}[1/7]${NC} ${GREEN}به‌روزرسانی سیستم و نصب پیش‌نیازها...${NC}"
apt update
apt upgrade -y
apt install -y python3 python3-pip python3-venv git screen build-essential libssl-dev

# ایجاد پوشه برای پروژه
echo -e "${BLUE}[2/7]${NC} ${GREEN}آماده‌سازی پوشه پروژه...${NC}"
mkdir -p /var/www/linkdoni
cd /var/www/linkdoni

# کلون کردن مخزن
echo -e "${BLUE}[3/7]${NC} ${GREEN}دانلود کد مخزن...${NC}"
git clone https://github.com/asanseir724/LinkHunterBot.git .

# ایجاد محیط مجازی پایتون
echo -e "${BLUE}[4/7]${NC} ${GREEN}راه‌اندازی محیط مجازی و نصب کتابخانه‌ها...${NC}"
python3 -m venv venv
source venv/bin/activate
pip install -r dependencies.txt

# ایجاد پوشه‌های مورد نیاز
echo -e "${BLUE}[5/7]${NC} ${GREEN}ایجاد پوشه‌های مورد نیاز...${NC}"
mkdir -p logs
mkdir -p static/exports
mkdir -p sessions
chmod 777 logs static/exports sessions

# درخواست اطلاعات لازم از کاربر
echo -e "${BLUE}[6/7]${NC} ${GREEN}تنظیم متغیرهای محیطی...${NC}"
echo ""
echo -e "${YELLOW}لطفاً اطلاعات زیر را وارد کنید:${NC}"
echo -e "${YELLOW}(در صورت خالی گذاشتن هر فیلد، مقدار پیش‌فرض استفاده می‌شود)${NC}"
echo ""

read -p "توکن ربات تلگرام (Telegram Bot Token): " BOT_TOKEN
BOT_TOKEN=${BOT_TOKEN:-"YOUR_BOT_TOKEN"}

read -p "شناسه حساب Twilio (Twilio Account SID) [اختیاری]: " TWILIO_SID
TWILIO_SID=${TWILIO_SID:-"YOUR_TWILIO_SID"}

read -p "توکن احراز هویت Twilio (Twilio Auth Token) [اختیاری]: " TWILIO_TOKEN
TWILIO_TOKEN=${TWILIO_TOKEN:-"YOUR_TWILIO_TOKEN"}

read -p "شماره تلفن Twilio (Twilio Phone Number) [اختیاری]: " TWILIO_PHONE
TWILIO_PHONE=${TWILIO_PHONE:-"YOUR_TWILIO_PHONE"}

SESSION_SECRET=$(openssl rand -hex 32)

# ایجاد فایل متغیرهای محیطی
cat > .env << EOL
# متغیرهای محیطی پروژه
SESSION_SECRET=${SESSION_SECRET}
TELEGRAM_BOT_TOKEN=${BOT_TOKEN}

# تنظیمات Twilio (در صورتیکه قصد استفاده از نوتیفیکیشن SMS را دارید)
TWILIO_ACCOUNT_SID=${TWILIO_SID}
TWILIO_AUTH_TOKEN=${TWILIO_TOKEN}
TWILIO_PHONE_NUMBER=${TWILIO_PHONE}
EOL

# اعمال متغیرهای محیطی
set -a
source .env
set +a

# نصب و راه‌اندازی Gunicorn به عنوان سرویس
echo -e "${BLUE}[7/7]${NC} ${GREEN}نصب و راه‌اندازی سرویس...${NC}"

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

# تغییر مالکیت پوشه
chown -R www-data:www-data /var/www/linkdoni

# فعال‌سازی و شروع سرویس
systemctl daemon-reload
systemctl enable linkdoni
systemctl start linkdoni

# نمایش پیام پایانی
echo ""
echo -e "${BLUE}==================================================${NC}"
echo -e "${GREEN}       نصب با موفقیت به پایان رسید!${NC}"
echo -e "${GREEN}       Installation Completed Successfully!${NC}"
echo -e "${BLUE}==================================================${NC}"
echo ""
echo -e "${YELLOW}سیستم لینک‌یاب تلگرام اکنون در حال اجراست.${NC}"
echo -e "${YELLOW}برای دسترسی به رابط وب به آدرس زیر مراجعه کنید:${NC}"
echo -e "${GREEN}http://$(hostname -I | awk '{print $1}'):5000${NC}"
echo ""
echo -e "${YELLOW}برای مشاهده وضعیت سرویس:${NC}"
echo -e "${GREEN}sudo systemctl status linkdoni${NC}"
echo ""
echo -e "${YELLOW}برای مشاهده لاگ‌های سیستم:${NC}"
echo -e "${GREEN}sudo journalctl -u linkdoni.service -f${NC}"
echo -e "${GREEN}tail -f /var/www/linkdoni/logs/bot.log${NC}"
echo ""