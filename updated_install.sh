#!/bin/bash
# نصب یک مرحله‌ای سیستم لینک‌یاب تلگرام
# One-Step Installation Script for Telegram Link Hunter Bot

# رنگ‌های مورد استفاده در خروجی
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # بدون رنگ

# مشخصات برنامه
VERSION="2.3.0"

# نمایش پیام خوش‌آمدگویی
echo -e "${BLUE}==================================================${NC}"
echo -e "${GREEN}      نصب یک مرحله‌ای سیستم لینک‌یاب تلگرام${NC}"
echo -e "${GREEN}      One-Step Link Hunter Bot Installation${NC}"
echo -e "${BLUE}--------------------------------------------------${NC}"
echo -e "${CYAN}                 نسخه ${VERSION}                ${NC}"
echo -e "${BLUE}==================================================${NC}"
echo ""

# بررسی اجرا با دسترسی root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}لطفاً این اسکریپت را با دسترسی root اجرا کنید:${NC}"
  echo -e "${YELLOW}sudo bash install.sh${NC}"
  exit 1
fi

# بررسی نصب بودن دیپندنسی‌های اولیه
echo -e "${BLUE}[1/8]${NC} ${GREEN}به‌روزرسانی سیستم و نصب پیش‌نیازها...${NC}"
apt update
apt upgrade -y
apt install -y python3 python3-pip python3-venv git screen build-essential libssl-dev libpq-dev python3-dev

# ایجاد پوشه برای پروژه
echo -e "${BLUE}[2/8]${NC} ${GREEN}آماده‌سازی پوشه پروژه...${NC}"
mkdir -p /var/www/linkdoni
cd /var/www/linkdoni

# انتخاب مخزن (GitHub یا GitLab)
echo -e "${BLUE}[3/8]${NC} ${GREEN}انتخاب منبع کد...${NC}"
echo -e "${YELLOW}از کدام مخزن می‌خواهید کد را دانلود کنید؟${NC}"
echo -e "${CYAN}1) GitHub (پیش‌فرض)${NC}"
echo -e "${CYAN}2) GitLab${NC}"
read -p "لطفاً گزینه مورد نظر را انتخاب کنید [1-2]: " REPO_CHOICE
REPO_CHOICE=${REPO_CHOICE:-1}

if [ "$REPO_CHOICE" == "1" ]; then
  echo -e "${GREEN}دانلود از مخزن GitHub...${NC}"
  REPO_URL="https://github.com/asanseir724/LinkHunterBot.git"
else
  echo -e "${GREEN}دانلود از مخزن GitLab...${NC}"
  read -p "آدرس مخزن GitLab را وارد کنید: " GITLAB_URL
  REPO_URL=${GITLAB_URL:-"https://gitlab.com/your-username/LinkHunterBot.git"}
fi

# کلون کردن مخزن
echo -e "${BLUE}[4/8]${NC} ${GREEN}دانلود کد مخزن...${NC}"
git clone $REPO_URL .

# ایجاد محیط مجازی پایتون
echo -e "${BLUE}[5/8]${NC} ${GREEN}راه‌اندازی محیط مجازی و نصب کتابخانه‌ها...${NC}"
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r dependencies.txt

# ایجاد پوشه‌های مورد نیاز
echo -e "${BLUE}[6/8]${NC} ${GREEN}ایجاد پوشه‌های مورد نیاز...${NC}"
mkdir -p logs
mkdir -p static/exports
mkdir -p sessions
mkdir -p php_version/sessions
chmod 777 logs static/exports sessions php_version/sessions

# درخواست اطلاعات لازم از کاربر
echo -e "${BLUE}[7/8]${NC} ${GREEN}تنظیم متغیرهای محیطی...${NC}"
echo ""
echo -e "${YELLOW}لطفاً اطلاعات زیر را وارد کنید:${NC}"
echo -e "${YELLOW}(در صورت خالی گذاشتن هر فیلد، مقدار پیش‌فرض استفاده می‌شود)${NC}"
echo ""

read -p "توکن ربات تلگرام (Telegram Bot Token): " BOT_TOKEN
BOT_TOKEN=${BOT_TOKEN:-"YOUR_BOT_TOKEN"}

# اطلاعات سرویس Twilio
echo -e "${CYAN}--- تنظیمات Twilio برای ارسال SMS (اختیاری) ---${NC}"
read -p "شناسه حساب Twilio (Account SID): " TWILIO_SID
TWILIO_SID=${TWILIO_SID:-"YOUR_TWILIO_SID"}

read -p "توکن احراز هویت Twilio (Auth Token): " TWILIO_TOKEN
TWILIO_TOKEN=${TWILIO_TOKEN:-"YOUR_TWILIO_TOKEN"}

read -p "شماره تلفن Twilio (Phone Number): " TWILIO_PHONE
TWILIO_PHONE=${TWILIO_PHONE:-"YOUR_TWILIO_PHONE"}

# اطلاعات سرویس‌های هوش مصنوعی
echo -e "${CYAN}--- تنظیمات هوش مصنوعی برای پاسخگویی خودکار (اختیاری) ---${NC}"
read -p "کلید API سرویس Avalai.ir: " AVALAI_KEY
AVALAI_KEY=${AVALAI_KEY:-"YOUR_AVALAI_API_KEY"}

read -p "کلید API سرویس Perplexity: " PERPLEXITY_KEY
PERPLEXITY_KEY=${PERPLEXITY_KEY:-"YOUR_PERPLEXITY_API_KEY"}

# تنظیمات پایگاه داده (اختیاری)
echo -e "${CYAN}--- تنظیمات پایگاه داده PostgreSQL (اختیاری) ---${NC}"
read -p "آیا می‌خواهید از PostgreSQL استفاده کنید؟ (y/n): " USE_POSTGRES
if [[ "$USE_POSTGRES" == "y" || "$USE_POSTGRES" == "Y" ]]; then
  read -p "آدرس سرور PostgreSQL: " DB_HOST
  DB_HOST=${DB_HOST:-"localhost"}
  
  read -p "نام کاربری PostgreSQL: " DB_USER
  DB_USER=${DB_USER:-"postgres"}
  
  read -p "رمز عبور PostgreSQL: " DB_PASS
  
  read -p "نام پایگاه داده: " DB_NAME
  DB_NAME=${DB_NAME:-"linkdoni"}
  
  DATABASE_URL="postgresql://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}"
else
  DATABASE_URL="sqlite:///links.db"
fi

SESSION_SECRET=$(openssl rand -hex 32)

# ایجاد فایل متغیرهای محیطی
cat > .env << EOL
# متغیرهای محیطی پروژه
SESSION_SECRET=${SESSION_SECRET}
TELEGRAM_BOT_TOKEN=${BOT_TOKEN}
DATABASE_URL=${DATABASE_URL}

# تنظیمات Twilio (برای ارسال نوتیفیکیشن SMS)
TWILIO_ACCOUNT_SID=${TWILIO_SID}
TWILIO_AUTH_TOKEN=${TWILIO_TOKEN}
TWILIO_PHONE_NUMBER=${TWILIO_PHONE}

# تنظیمات هوش مصنوعی (برای پاسخگویی خودکار به پیام‌های خصوصی)
AVALAI_API_KEY=${AVALAI_KEY}
PERPLEXITY_API_KEY=${PERPLEXITY_KEY}
EOL

# اعمال متغیرهای محیطی
set -a
source .env
set +a

# بررسی نصب بودن PHP
echo -e "${YELLOW}بررسی نصب PHP برای پشتیبانی از MadelineProto...${NC}"
if ! command -v php &> /dev/null; then
  echo -e "${CYAN}PHP یافت نشد. در حال نصب PHP 8.2 و افزونه‌های مورد نیاز...${NC}"
  apt install -y software-properties-common
  add-apt-repository -y ppa:ondrej/php
  apt update
  apt install -y php8.2 php8.2-cli php8.2-common php8.2-curl php8.2-gd php8.2-mbstring php8.2-mysql php8.2-xml php8.2-zip php8.2-bcmath
else
  PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
  echo -e "${GREEN}PHP ${PHP_VERSION} یافت شد.${NC}"
  if (( $(echo "$PHP_VERSION < 8.0" | bc -l) )); then
    echo -e "${YELLOW}نسخه PHP کمتر از 8.0 است. توصیه می‌شود PHP 8.2 را نصب کنید.${NC}"
    read -p "آیا می‌خواهید PHP 8.2 را نصب کنید؟ (y/n): " INSTALL_PHP82
    if [[ "$INSTALL_PHP82" == "y" || "$INSTALL_PHP82" == "Y" ]]; then
      apt install -y software-properties-common
      add-apt-repository -y ppa:ondrej/php
      apt update
      apt install -y php8.2 php8.2-cli php8.2-common php8.2-curl php8.2-gd php8.2-mbstring php8.2-mysql php8.2-xml php8.2-zip php8.2-bcmath
    fi
  fi
fi

# نصب Composer برای MadelineProto
if [ ! -f "composer.phar" ]; then
  echo -e "${CYAN}نصب Composer برای پشتیبانی از MadelineProto...${NC}"
  curl -sS https://getcomposer.org/installer | php
  php composer.phar install
fi

# نصب و راه‌اندازی Gunicorn به عنوان سرویس
echo -e "${BLUE}[8/8]${NC} ${GREEN}نصب و راه‌اندازی سرویس...${NC}"

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
echo -e "${YELLOW}ویژگی‌های فعال شده در این نصب:${NC}"
echo -e "${GREEN}✓ استخراج خودکار لینک از کانال‌های تلگرام${NC}"
echo -e "${GREEN}✓ جمع‌آوری لینک‌ها از وب‌سایت‌ها${NC}"
echo -e "${GREEN}✓ مدیریت حساب‌های کاربری تلگرام${NC}"
echo -e "${GREEN}✓ پشتیبانی از ارسال پیامک با Twilio${NC}"
echo -e "${GREEN}✓ پاسخگویی هوشمند با Avalai.ir و Perplexity${NC}"
echo -e "${GREEN}✓ رابط کاربری شبیه تلگرام دسکتاپ${NC}"
echo -e "${GREEN}✓ اتصال دسته‌ای همه اکانت‌ها${NC}"
echo -e "${GREEN}✓ پشتیبانی از دو زبان فارسی و انگلیسی${NC}"
echo ""
echo -e "${YELLOW}برای مشاهده وضعیت سرویس:${NC}"
echo -e "${GREEN}sudo systemctl status linkdoni${NC}"
echo ""
echo -e "${YELLOW}برای مشاهده لاگ‌های سیستم:${NC}"
echo -e "${GREEN}sudo journalctl -u linkdoni.service -f${NC}"
echo -e "${GREEN}tail -f /var/www/linkdoni/logs/bot.log${NC}"
echo ""
echo -e "${YELLOW}برای اطلاعات و راهنمای بیشتر به README.md مراجعه کنید${NC}"
echo -e "${GREEN}https://github.com/asanseir724/LinkHunterBot#readme${NC}"
echo ""
echo -e "${PURPLE}با تشکر از انتخاب سیستم لینک‌یاب تلگرام${NC}"
echo -e "${PURPLE}Thank you for choosing Telegram Link Hunter Bot${NC}"
echo ""