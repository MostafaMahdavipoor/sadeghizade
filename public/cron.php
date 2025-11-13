<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/AppConfig.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BotHandler.php';
require_once __DIR__ . '/../classes/Functions.php';
require_once __DIR__ . '/../classes/HandleRequest.php';
require_once __DIR__ . '/../classes/FileHandler.php';
// و سایر فایل‌های مورد نیاز...

use Bot\Database;     // <-- اصلاح شد
use Bot\BotHandler;   // <-- اصلاح شد
use Config\AppConfig; // (این مورد احتمالاً درست است چون در فایل AppConfig.php قرار دارد)

date_default_timezone_set('Asia/Tehran');

$db = new Database(); // <-- اکنون به درستی Bot\Database را می‌سازد
// ...
$botHandler = new BotHandler(null, null, null, null); // <-- اکنون به درستی Bot\BotHandler را می‌سازد
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
    $text = "ههی {$student['first_name']} 😏 \n";
    $text .= "انگار وقت گزارش دادن شده!  \n";
    $text .= "نذار یادآوری بعدی با اخم بیاد 😅 \n ";
    $text .= "بدو یکی از گزینه‌های زیر رو بزن و خلاص شو 😜 \n";


    $buttons = [
        [['text' => '🔥 بریم گزارش بدیم!', 'callback_data' => 'start_daily_report']],
        [['text' => '🥹 امروز نخوندم', 'callback_data' => 'no_study_today']]
    ];


    $botHandler->sendRequest("sendMessage", [
        "chat_id" => $chatId,
        "text" => $text,
        "reply_markup" => json_encode(['inline_keyboard' => $buttons])
    ]);
}


// --- ۲. ارسال پیام یادآوری (یک ساعت بعد) ---
$studentsToRemind = $db->getUsersToRemind(); // این متد در Database.php اضافه شد

foreach ($studentsToRemind as $report) {
    $chatId = $report['chat_id'];
    $reportId = $report['report_id'];
    $student = $db->getStudent($chatId); // یا متد دیگری که اطلاعات دانش‌آموز را برگرداند
    echo "Sending reminder to chat_id: $chatId for report_id: $reportId\n";

    // ۱. ارسال پیام یادآوری
    $text = "📣 هی {$student['first_name']}!\n";
    $text .= "ما هنوز منتظر گزارش امروزتیم 😏 \n";
    $text .= "نذار فردا من خودم بیام دنبالت 😆 \n ";
    $text .= "زودتر یکی از گزینه‌ها رو بزن ✍️ \n";


    $buttons = [
        [['text' => '🔥 بریم گزارش بدیم!', 'callback_data' => 'start_daily_report']],
        [['text' => '🥹 امروز نخوندم', 'callback_data' => 'no_study_today']]
    ];


    $botHandler->sendRequest("sendMessage", [
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => "Markdown",
        "reply_markup" => json_encode(['inline_keyboard' => $buttons])
    ]);

    // ۲. به‌روزرسانی دیتابیس که یادآوری ارسال شده
    $db->updateReportReminderSent($reportId);
}

echo "Cron job finished.\n";
