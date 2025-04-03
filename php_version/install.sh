#!/bin/bash

# رنگ‌های مورد استفاده در خروجی
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== نصب استخراج کننده لینک تلگرام (نسخه PHP) ===${NC}\n"

# چک کردن PHP
if command -v php &> /dev/null; then
    php_version=$(php -v | head -n 1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    echo -e "${GREEN}✓ PHP نصب شده است (نسخه $php_version)${NC}"
    
    # چک کردن نسخه PHP
    if (( $(echo "$php_version < 8.0" | bc -l) )); then
        echo -e "${RED}✗ نسخه PHP باید 8.0 یا بالاتر باشد.${NC}"
        exit 1
    fi
else
    echo -e "${RED}✗ PHP یافت نشد. لطفا PHP 8.0 یا بالاتر را نصب کنید.${NC}"
    exit 1
fi

# چک کردن Composer
if command -v composer &> /dev/null; then
    echo -e "${GREEN}✓ Composer نصب شده است${NC}"
else
    echo -e "${BLUE}در حال نصب Composer...${NC}"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=. --filename=composer
    php -r "unlink('composer-setup.php');"
    echo -e "${GREEN}✓ Composer نصب شد${NC}"
fi

# ایجاد پوشه‌های مورد نیاز
echo -e "${BLUE}در حال ایجاد پوشه‌های مورد نیاز...${NC}"
mkdir -p sessions exports templates
chmod 777 sessions exports
echo -e "${GREEN}✓ پوشه‌های مورد نیاز ایجاد شدند${NC}"

# کپی فایل تنظیمات محیطی
if [ ! -f .env ]; then
    echo -e "${BLUE}در حال کپی فایل تنظیمات محیطی...${NC}"
    cp .env.example .env
    echo -e "${GREEN}✓ فایل .env ایجاد شد${NC}"
    echo -e "${BLUE}⚠️ لطفا فایل .env را ویرایش کرده و مقادیر واقعی را وارد کنید.${NC}"
fi

# نصب وابستگی‌ها
echo -e "${BLUE}در حال نصب وابستگی‌های PHP...${NC}"
if [ -f composer ]; then
    php composer install
else
    composer install
fi
echo -e "${GREEN}✓ وابستگی‌های PHP نصب شدند${NC}"

echo -e "\n${GREEN}=== نصب با موفقیت انجام شد ===${NC}"
echo -e "${BLUE}برای اجرای برنامه، دستور زیر را وارد کنید:${NC}"
echo -e "php -S 0.0.0.0:5000 -t public"