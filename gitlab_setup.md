# راهنمای اضافه کردن پروژه به GitLab

## پیش‌نیازها
1. حساب کاربری در GitLab
2. گیت نصب شده روی سیستم محلی

## مراحل

### 1. ایجاد مخزن جدید در GitLab
1. وارد حساب GitLab خود شوید (https://gitlab.com)
2. روی دکمه "New project/repository" کلیک کنید
3. گزینه "Create blank project" را انتخاب کنید
4. نام پروژه را وارد کنید (مثلاً "LinkHunterBot")
5. توضیحات پروژه را وارد کنید (اختیاری)
6. می‌توانید visibility level را خصوصی یا عمومی تنظیم کنید
7. تیک "Initialize repository with a README" را **غیرفعال** کنید
8. روی "Create project" کلیک کنید

### 2. ایجاد توکن دسترسی (برای روش HTTPS)
1. در GitLab، به Settings -> Access Tokens بروید (از طریق نمایه کاربری خود)
2. نام توکن را وارد کنید (مثلاً "LinkHunterBot Push Access")
3. تاریخ انقضا تنظیم کنید (اختیاری)
4. در قسمت Scopes، گزینه‌های "api" و "write_repository" را انتخاب کنید
5. روی "Create personal access token" کلیک کنید
6. توکن تولید شده را کپی کنید و در جای امنی نگهداری کنید (این توکن فقط یک بار نمایش داده می‌شود)

### 3. تنظیم دسترسی SSH (روش جایگزین، اختیاری)
روش SSH امن‌تر است و نیازی به وارد کردن نام کاربری/رمز عبور در هر بار push ندارد:

1. کلید SSH ایجاد کنید (اگر قبلاً ندارید):
   ```bash
   ssh-keygen -t ed25519 -C "your_email@example.com"
   ```
2. کلید عمومی را نمایش دهید:
   ```bash
   cat ~/.ssh/id_ed25519.pub
   ```
3. محتوای آن را کپی کنید
4. به Settings -> SSH Keys در حساب GitLab خود بروید
5. کلید را paste کنید و روی "Add key" کلیک کنید

### 4. تنظیم مخزن محلی و ارسال به GitLab

#### روش HTTPS (با استفاده از توکن دسترسی):
```bash
# افزودن remote جدید به نام gitlab
git remote add gitlab https://oauth2:{YOUR_ACCESS_TOKEN}@gitlab.com/your-username/LinkHunterBot.git

# ارسال کد به GitLab
git push -u gitlab main
```
(توجه: {YOUR_ACCESS_TOKEN} را با توکن دسترسی خود و your-username را با نام کاربری GitLab خود جایگزین کنید)

#### روش SSH (اگر کلید SSH تنظیم کرده‌اید):
```bash
# افزودن remote جدید به نام gitlab
git remote add gitlab git@gitlab.com:your-username/LinkHunterBot.git

# ارسال کد به GitLab
git push -u gitlab main
```

### 5. بررسی نتیجه
پس از اجرای دستورات بالا، به صفحه مخزن خود در GitLab بروید و مطمئن شوید که کدها با موفقیت آپلود شده‌اند.

### 6. استفاده همزمان از GitLab و GitHub
اکنون پروژه شما هم در GitHub و هم در GitLab موجود است. برای به‌روزرسانی هر دو مخزن، می‌توانید از دستورات زیر استفاده کنید:

```bash
# ارسال تغییرات به GitHub
git push origin main

# ارسال تغییرات به GitLab
git push gitlab main
```

یا برای ارسال به هر دو با یک دستور:
```bash
git push origin main && git push gitlab main
```
