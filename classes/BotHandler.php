<?php

namespace Bot;

use Config\AppConfig;
use Payment\ZarinpalPaymentHandler;

class BotHandler
{
    use HandleRequest;
    use Functions;
    private $chatId;
    private $text;
    private $messageId;
    private $message;
    public $db;
    private $fileHandler;
    private $zarinpalPaymentHandler;
    private $botToken;
    private $botLink;

    public function __construct($chatId, $text, $messageId, $message)
    {
        $this->chatId = $chatId;
        $this->text = $text;
        $this->messageId = $messageId;
        $this->message = $message;
        $this->db = new Database();
        $this->fileHandler = new FileHandler(); // از فایل هندلر جدید (تک فایلی) استفاده می‌کند
        $config = AppConfig::get();
        $this->botToken = $config['bot']['token'];
        $this->botLink = $config['bot']['bot_link'];
    }

    public function deleteMessageWithDelay(): void
    {
        $this->sendRequest("deleteMessage", [
            "chat_id" => $this->chatId,
            "message_id" => $this->messageId
        ]);
    }

    public function handleSuccessfulPayment($update): void
    {
        if (isset($update['message']['successful_payment'])) {
            $chatId = $update['message']['chat']['id'];
            $payload = $update['message']['successful_payment']['invoice_payload'];
            $successfulPayment = $update['message']['successful_payment'];
        }
    }

    public function handlePreCheckoutQuery($update): void
    {
        if (isset($update['pre_checkout_query'])) {
            $query_id = $update['pre_checkout_query']['id'];
            file_put_contents('log.txt', date('Y-m-d H:i:s') . " - Received pre_checkout_query: " . print_r($update, true) . "\n", FILE_APPEND);
            $url = "https://api.telegram.org/bot" . $this->botToken . "/answerPreCheckoutQuery";
            $post_fields = [
                'pre_checkout_query_id' => $query_id,
                'ok' => true,
                'error_message' => ""
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            curl_close($ch);
            file_put_contents('log.txt', date('Y-m-d H:i:s') . " - answerPreCheckoutQuery Response: " . print_r(json_decode($response, true), true) . "\n", FILE_APPEND);
        }
    }

    public function handleCallbackQuery($callbackQuery): void
    {
        $callbackData = $callbackQuery["data"] ?? null;
        $chatId = $callbackQuery["message"]["chat"]["id"] ?? null;
        $callbackQueryId = $callbackQuery["id"] ?? null;
        $messageId = $callbackQuery["message"]["message_id"] ?? null;
        $user = $callbackQuery['from'] ?? null;

        if (!$callbackData || !$chatId || !$callbackQueryId || !$messageId || !$user) {
            error_log("Callback query missing required data.");
            if ($callbackQueryId) $this->answerCallbackQuery($callbackQueryId, "خطا!", true);
            return;
        }

        // به‌روزرسانی اطلاعات کاربر
        $this->db->saveUser($user);

        // بازخوانی chatId و messageId از کالبک (مطمئن‌تر است)
        $this->chatId = $chatId;
        $this->messageId = $messageId;

        // ** مدیریت حالت‌ها (States) **
        // --- اصلاح شده: رفع باگ $stateData و استفاده از FileHandler جدید ---
        $state = $this->fileHandler->getState($this->chatId);
        $data = $this->fileHandler->getData($this->chatId);
        // --- پایان اصلاح ---


        // --- مدیریت ثبت نام ---
        if (str_starts_with($callbackData, 'set_major_')) {
            if ($state !== 'awaiting_major') {
                $this->answerCallbackQuery($callbackQueryId, "مرحله ثبت نام منقضی شده است. لطفا از /start register مجدد شروع کنید.", true);
                return;
            }
            $data['major'] = substr($callbackData, 10); // 'tajrobi' or 'riazi'
            $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
            $this->fileHandler->saveState($this->chatId, 'awaiting_grade'); // اصلاح شد
            $this->askGrade($messageId); //
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }

        if (str_starts_with($callbackData, 'set_grade_')) {
            if ($state !== 'awaiting_grade') {
                $this->answerCallbackQuery($callbackQueryId, "مرحله ثبت نام منقضی شده است.", true);
                return;
            }
            $data['grade'] = substr($callbackData, 10); // '10', '11', '12'
            $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
            $this->fileHandler->saveState($this->chatId, 'awaiting_report_time'); // اصلاح شد
            $this->askReportTime($messageId); //
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }

        if (str_starts_with($callbackData, 'set_time_')) {
            if ($state !== 'awaiting_report_time') {
                $this->answerCallbackQuery($callbackQueryId, "مرحله ثبت نام منقضی شده است.", true);
                return;
            }
            $data['time'] = substr($callbackData, 9); // '19:00:00', ...

            // ** پایان ثبت نام **
            $this->db->finalizeStudentRegistration(
                $this->chatId,
                $data['first_name'] ?? 'ناشناس',
                $data['last_name'] ?? '',
                $data['major'],
                $data['grade'],
                $data['time']
            );

            $this->fileHandler->saveState($this->chatId, null); // اصلاح شد: پاک کردن حالت
            $this->sendRequest(
                "editMessageText",
                [
                    "chat_id" => $this->chatId,
                    "message_id" =>  $messageId,
                    "text" => "ثبت نام شما با موفقیت انجام شد."
                ]
            );

            $this->showMainMenu($this->db->isAdmin($this->chatId)); //

            // اطلاع به ادمین‌ها
            $this->notifyAdminsOfRegistration($this->chatId, $data); //
            $this->answerCallbackQuery($callbackQueryId, "ثبت نام تکمیل شد.", false);
            return;
        }

        if ($callbackData === 'cancell') {
            $this->showMainMenu($this->db->isAdmin($this->chatId), $messageId);

            return;
        }
        if ($callbackData === 'start_daily_report') {
            $report = $this->db->getTodaysReport($this->chatId);
            if (!$report) {
                $this->answerCallbackQuery($callbackQueryId, "هنوز زمان گزارش شما فرا نرسیده است.", true);
                return;
            }
            $this->fileHandler->saveState($this->chatId, 'awaiting_lesson_name'); // اصلاح شد
            // دیتا را برای گزارش جدید آماده می‌کنیم
            $this->fileHandler->saveData($this->chatId, [ // اصلاح شد
                'report_id' => $report['report_id'],
                'current_entry' => []
            ]);

            $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "درس مورد نظر را وارد کنید:"]);
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }

        if ($callbackData === 'no_study_today') {
            $report = $this->db->getTodaysReport($this->chatId);
            if (!$report) {
                $this->answerCallbackQuery($callbackQueryId, "خطا در یافتن گزارش امروز.", true);
                return;
            }
            $this->fileHandler->saveState($this->chatId, 'awaiting_no_study_reason'); // اصلاح شد
            $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "لطفا دلیل درس نخواندن خود را (متن یا عکس) ارسال کنید:"]);
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }

        if ($callbackData === 'no_test') {
            if ($state !== 'awaiting_test_count') { // فقط در صورتی که منتظر تعداد تست بودیم عمل کن
                $this->answerCallbackQuery($callbackQueryId);
                return;
            }

            $data['current_entry']['test_count'] = 0;
            $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
            $this->fileHandler->saveState($this->chatId, 'awaiting_report_decision'); // اصلاح شد
            $this->showEntrySummary($data['current_entry']); //
            $this->answerCallbackQuery($callbackQueryId, "تست نزدم ثبت شد.");
            return;
        }

        if ($callbackData === 'add_next_subject') {
            if ($state !== 'awaiting_report_decision') {
                $this->answerCallbackQuery($callbackQueryId);
                return;
            }
            // 1. ذخیره درس فعلی
            $this->saveCurrentEntryToDb($data); //

            // 2. آماده شدن برای درس بعدی
            $data['current_entry'] = []; // خالی کردن ورودی فعلی
            $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
            $this->fileHandler->saveState($this->chatId, 'awaiting_lesson_name'); // اصلاح شد

            $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "درس بعدی را وارد کنید:"]);
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }

        if ($callbackData === 'finish_report') {
            if ($state !== 'awaiting_report_decision') {
                $this->answerCallbackQuery($callbackQueryId);
                return;
            }
            // 1. ذخیره آخرین درس
            $this->saveCurrentEntryToDb($data); //

            // 2. اتمام گزارش
            $reportId = $data['report_id'];
            $this->db->updateReportStatus($reportId, 'submitted');
            $this->fileHandler->saveState($this->chatId, null); // اصلاح شد: پاک کردن حالت

            $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "گزارش شما با موفقیت ثبت شد. ممنون!"]);
            $this->answerCallbackQuery($callbackQueryId, "گزارش ثبت شد.");

            // 3. اطلاع به ادمین
            $this->notifyAdminsOfFullReport($reportId); //
            return;
        }

        // اگر هیچکدام از موارد بالا نبود، فقط به کالبک پاسخ بده که لودینگ تمام شود
        $this->answerCallbackQuery($callbackQueryId);
    }

    public function sendRequest($method, $data)
    {
        $url = "https://api.telegram.org/bot" . $this->botToken . "/$method";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        $this->logTelegramRequest($method, $data, $response, $httpCode, $curlError);
        if ($curlError) {
            return false;
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        } else {
            $errorResponse = json_decode($response, true);
            $errorMessage = $errorResponse['description'] ?? 'Unknown error';
            return false;
        }
    }

    private function logTelegramRequest($method, $data, $response, $httpCode, $curlError = null): void
    {
        $logData = [
            'time' => date("Y-m-d H:i:s"),
            'method' => $method,
            'request_data' => $data,
            'response' => $response,
            'http_code' => $httpCode,
            'curl_error' => $curlError
        ];
        // این لاگ به جایی نوشته نمی‌شود، می‌توانید آن را در فایل بنویسید یا از کلاس Logger استفاده کنید
        $logMessage = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
