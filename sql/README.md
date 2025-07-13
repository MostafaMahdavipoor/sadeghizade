# Database Setup for Telegram Bot

این پوشه شامل فایل‌های SQL مورد نیاز برای راه‌اندازی پایگاه داده ربات تلگرام است.

## فایل‌های موجود

### 1. `01_database_schema.sql`
فایل اصلی ساختار پایگاه داده که شامل:
- ایجاد دیتابیس `telegram_bot_db`
- جداول اصلی: `users`, `chat_history`, `broadcasts`, `broadcast_recipients`, `settings`, `logs`
- ایندکس‌های بهینه‌سازی
- Foreign Key ها

### 2. `02_initial_data.sql`
داده‌های اولیه و تنظیمات پیش‌فرض:
- تنظیمات پیش‌فرض ربات
- کاربر ادمین نمونه
- View ها برای گزارش‌گیری
- Stored Procedure ها برای عملیات رایج
- Trigger ها برای به‌روزرسانی خودکار

### 3. `03_sample_data.sql`
داده‌های نمونه برای تست و توسعه:
- کاربران نمونه (ادمین و عادی)
- تاریخچه چت نمونه
- پیام‌های همگانی نمونه
- لاگ‌های نمونه

## نحوه استفاده

### نصب کامل پایگاه داده:
```bash
# 1. ایجاد ساختار پایگاه داده
mysql -u username -p < sql/01_database_schema.sql

# 2. اضافه کردن داده‌های اولیه
mysql -u username -p < sql/02_initial_data.sql

# 3. اضافه کردن داده‌های نمونه (اختیاری)
mysql -u username -p < sql/03_sample_data.sql
```

### یا اجرای همه فایل‌ها به ترتیب:
```bash
mysql -u username -p < sql/01_database_schema.sql && \
mysql -u username -p < sql/02_initial_data.sql && \
mysql -u username -p < sql/03_sample_data.sql
```

## جداول اصلی

### `users`
جدول کاربران ربات:
- `chat_id`: شناسه چت تلگرام (منحصر به فرد)
- `username`: نام کاربری تلگرام
- `first_name`, `last_name`: نام و نام خانوادگی
- `language`: زبان کاربر (fa/en)
- `is_admin`: وضعیت ادمین
- `status`: وضعیت کاربر (active/blocked/pending)

### `chat_history`
تاریخچه چت با هوش مصنوعی:
- `chat_id`: شناسه چت کاربر
- `role`: نقش پیام (user/assistant)
- `content_type`: نوع محتوا (text/image/document/voice/video)
- `content`: محتوای پیام
- `file_id`: شناسه فایل تلگرام (در صورت وجود)

### `broadcasts`
پیام‌های همگانی:
- `title`: عنوان پیام
- `message`: متن پیام
- `button_text`, `button_link`: دکمه و لینک (اختیاری)
- `status`: وضعیت (draft/sending/completed/cancelled)
- `created_by`: شناسه چت ادمین ایجادکننده

### `broadcast_recipients`
گیرندگان پیام‌های همگانی:
- `broadcast_id`: شناسه پیام همگانی
- `user_id`: شناسه چت کاربر
- `status`: وضعیت ارسال (pending/sent/failed)
- `message_id`: شناسه پیام تلگرام ارسال شده

### `settings`
تنظیمات ربات:
- `key`: کلید تنظیم
- `value`: مقدار تنظیم
- `description`: توضیحات
- `type`: نوع داده (string/integer/boolean/json)

### `logs`
لاگ‌های سیستم:
- `level`: سطح لاگ (info/success/warning/error)
- `title`: عنوان رویداد
- `message`: پیام لاگ
- `context`: اطلاعات اضافی (JSON)
- `chat_id`: شناسه چت مرتبط (در صورت وجود)

## View های مفید

### `v_user_stats`
آمار کاربران شامل تعداد پیام‌ها و پاسخ‌های AI

### `v_broadcast_stats`
آمار پیام‌های همگانی شامل تعداد گیرندگان و وضعیت ارسال

## Stored Procedure های مفید

### `GetUserWithDetails(chat_id)`
دریافت اطلاعات کامل کاربر همراه با آمار

### `GetChatHistory(chat_id, limit, offset)`
دریافت تاریخچه چت کاربر با قابلیت صفحه‌بندی

### `CreateBroadcast(title, message, button_text, button_link, created_by)`
ایجاد پیام همگانی جدید

### `AddBroadcastRecipients(broadcast_id, user_ids)`
اضافه کردن گیرندگان به پیام همگانی

## تنظیمات پیش‌فرض

- `bot_welcome_message`: پیام خوش‌آمدگویی
- `admin_chat_id`: شناسه چت ادمین اصلی
- `ai_chat_enabled`: فعال بودن چت هوش مصنوعی
- `ai_model`: مدل هوش مصنوعی پیش‌فرض
- `broadcast_delay`: تاخیر بین ارسال پیام‌های همگانی
- `max_chat_history`: حداکثر تعداد پیام در تاریخچه

## نکات مهم

1. **امنیت**: حتماً رمز عبور قوی برای کاربر دیتابیس تنظیم کنید
2. **پشتیبان‌گیری**: قبل از نصب، از دیتابیس موجود پشتیبان تهیه کنید
3. **تنظیمات**: پس از نصب، تنظیمات را مطابق نیاز خود تغییر دهید
4. **تست**: داده‌های نمونه برای تست استفاده کنید و در محیط تولید حذف کنید

## عیب‌یابی

### خطای "Access denied":
- بررسی نام کاربری و رمز عبور
- بررسی مجوزهای کاربر دیتابیس

### خطای "Database already exists":
- دیتابیس قبلاً وجود دارد، می‌توانید از `USE telegram_bot_db;` استفاده کنید

### خطای "Table already exists":
- جداول قبلاً ایجاد شده‌اند، فایل‌های 02 و 03 را اجرا کنید

## پشتیبانی

در صورت بروز مشکل، لاگ‌های سیستم را بررسی کنید:
```sql
SELECT * FROM logs ORDER BY created_at DESC LIMIT 10;
``` 