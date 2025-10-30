<?php


require_once __DIR__ . '/../vendor/autoload.php'; 
require_once __DIR__ . '/../config/AppConfig.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BotHandler.php';
require_once __DIR__ . '/../classes/Functions.php';
require_once __DIR__ . '/../classes/HandleRequest.php';
require_once __DIR__ . '/../classes/FileHandler.php';
// و سایر فایل‌های مورد نیاز...

use Bot\Database;
use Bot\BotHandler;
use Config\AppConfig;

date_default_timezone_set('Asia/Tehran');

$db = new Database();
$config = AppConfig::get();
$botToken = $config['bot']['token'];

// یک نمونه BotHandler می‌سازیم فقط برای دسترسی به متد sendRequest
// ما به chatId و ... نیاز نداریم چون مستقیماً از دیتابیس می‌خوانیم
$botHandler = new BotHandler(null, null, null, null);


$currentTime = date('H:i:s'); 
$currentDate = date('Y-m-d');
echo "Checking for notifications due on $currentDate at or before $currentTime\n";

$studentsToNotify = $db->getUsersToNotify($currentTime, $currentDate);

foreach ($studentsToNotify as $student) {
    $chatId = $student['chat_id'];
    
    // دیگر نیازی به چک کردن $existingReport نیست، چون کوئری دیتابیس این کار را کرد

    echo "Notifying chat_id: $chatId\n";

    // ۱. ساخت ردیف گزارش در دیتابیس
    // از $currentDate استفاده می‌کنیم که مطمئن باشیم تاریخ درست است
    $db->createDailyReport($chatId, $currentDate, date('Y-m-d H:i:s'));

    // ۲. ارسال پیام به دانش آموز
    $text = "سلام! وقت ثبت گزارش روزانه‌ات رسیده. ✍️\n\nلطفا یکی از گزینه‌های زیر را انتخاب کن:";
    $buttons = [
        [['text' => '✅ ثبت گزارش امروز', 'callback_data' => 'start_daily_report']],
        [['text' => '❌ امروز درس نخواندم', 'callback_data' => 'no_study_today']]
    ];

    $botHandler->sendRequest("sendMessage", [
        "chat_id" => $chatId,
        "text" => $text,
        "reply_markup" => json_encode(['inline_keyboard' => $buttons])
    ]);
}


// --- ۲. ارسال پیام یادآوری (یک ساعت بعد) ---
echo "Checking for reminders...\n";
$studentsToRemind = $db->getUsersToRemind(); // این متد در Database.php اضافه شد

foreach ($studentsToRemind as $report) {
    $chatId = $report['chat_id'];
    $reportId = $report['report_id'];

    echo "Sending reminder to chat_id: $chatId for report_id: $reportId\n";

    // ۱. ارسال پیام یادآوری
    $text = "⚠️ **یادآوری:**\n\nشما هنوز گزارش امروز خود را ثبت نکرده‌اید! لطفاً هرچه سریع‌تر اقدام کنید.";
    $botHandler->sendRequest("sendMessage", [
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => "Markdown"
    ]);

    // ۲. به‌روزرسانی دیتابیس که یادآوری ارسال شده
    $db->updateReportReminderSent($reportId);
}

echo "Cron job finished.\n";