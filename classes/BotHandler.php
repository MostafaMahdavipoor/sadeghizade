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
private function buildLessonButtons(array $lessons, int $columns = 2): array
    {
        $buttons = [];
        $row = [];
        foreach ($lessons as $lesson) {
            $row[] = ['text' => $lesson['name'], 'callback_data' => 'select_lesson_' . $lesson['lesson_id']];
            if (count($row) >= $columns) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $buttons[] = $row; // افزودن ردیف آخر
        }
        return $buttons;
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

        if (str_starts_with($callbackData, 'wizard_')) {
            $data = $this->fileHandler->getData($this->chatId);
            // چک می‌کنیم که واقعا در ویزارد ثبت نام باشیم
            if (isset($data['wizard']) && $data['wizard'] === 'registration') {
                // $messageId از خود کالبک می‌آید و همان آیدی پیام ویزارد است
                $this->processWizard($callbackData, true, $messageId);
                $this->answerCallbackQuery($callbackQueryId);
                return;
            }
            // (می‌توانید برای ویزاردهای دیگر هم else if بگذارید)
        }

        if ($callbackData === 'cancell') {
            $data = $this->fileHandler->getData($this->chatId);
            if (isset($data['wizard'])) {
                $this->processWizard('wizard_cancel', true, $messageId);
                $this->answerCallbackQuery($callbackQueryId, "ثبت نام لغو شد.");
                return;
            }

            $this->showMainMenu($this->db->isAdmin($this->chatId), $messageId);
            return;
        }
        if ($callbackData === 'go_to_main_menu') {
            $this->showMainMenu($this->db->isAdmin($this->chatId), $messageId);
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }

      if ($callbackData === 'start_daily_report') {
            $report = $this->db->getTodaysReport($this->chatId);
            if (!$report) {
                $this->answerCallbackQuery($callbackQueryId, "هنوز زمان گزارش شما فرا نرسیده است.", true);
                return;
            }

            // --- شروع تغییر ---
            // 1. رشته دانش‌آموز را بگیر
            $major = $this->db->getStudentMajor($this->chatId);
            if (!$major) {
                $this->answerCallbackQuery($callbackQueryId, "خطا: اطلاعات رشته شما یافت نشد. برای ثبت‌نام مجدد /start register را بزنید.", true);
                return;
            }

            // 2. دروس اصلی را از دیتابیس بخوان (parent_id = null)
            $mainLessons = $this->db->getLessons(null, $major);
            if (empty($mainLessons)) {
                $this->answerCallbackQuery($callbackQueryId, "خطا: درسی برای رشته شما در سیستم تعریف نشده است.", true);
                return;
            }

            // 3. دیتا را برای گزارش جدید آماده کن
            $this->fileHandler->saveData($this->chatId, [ 
                'report_id' => $report['report_id'],
                'current_entry' => []
            ]);
            // 4. یک حالت کلی برای فرآیند گزارش تنظیم کن
            $this->fileHandler->saveState($this->chatId, 'awaiting_report_input'); 

            // 5. دکمه‌ها را بساز و ارسال کن
            $buttons = $this->buildLessonButtons($mainLessons); 

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId, 
                "text" => "✍️ لطفا درس اصلی را انتخاب کنید:",
                "reply_markup" => json_encode(['inline_keyboard' => $buttons])
            ]);
            $this->answerCallbackQuery($callbackQueryId);
            return;
            // --- پایان تغییر ---
        }
        
        // --- بلاک جدید برای مدیریت انتخاب درس ---
        if (str_starts_with($callbackData, 'select_lesson_')) {
            // اطمینان از اینکه کاربر در فرآیند گزارش‌دهی است
            if ($state !== 'awaiting_report_input') {
                $this->answerCallbackQuery($callbackQueryId); // پاسخ بده که لودینگ تمام شود
                return;
            }

            $lessonId = (int)substr($callbackData, strlen('select_lesson_'));
            
            $lesson = $this->db->getLessonById($lessonId);
            if (!$lesson) {
                $this->answerCallbackQuery($callbackQueryId, "خطا: درس یافت نشد.", true);
                return;
            }

            $major = $this->db->getStudentMajor($this->chatId);
            $subLessons = $this->db->getLessons($lessonId, $major);
            $data = $this->fileHandler->getData($this->chatId);

            if (!empty($subLessons)) {
                // --- این درس والد است، زیرمجموعه‌ها را نشان بده ---
                
                // پیشوند نام درس (مثلا "فیزیک - ") را در دیتا ذخیره می‌کنیم
                $data['current_entry']['lesson_prefix'] = ($data['current_entry']['lesson_prefix'] ?? '') . $lesson['name'] . " - ";
                $this->fileHandler->saveData($this->chatId, $data);

                $buttons = $this->buildLessonButtons($subLessons);
                // پیام قبلی را ویرایش می‌کنیم تا دکمه‌های جدید نشان داده شوند
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $this->messageId,
                    "text" => "زیرمجموعه '" . htmlspecialchars($lesson['name']) . "' را انتخاب کنید:",
                    "reply_markup" => json_encode(['inline_keyboard' => $buttons])
                ]);

            } else {
                // --- این درس نهایی است، به مرحله بعد (پرسیدن مبحث) برو ---
                
                // نام کامل درس را می‌سازیم (مثلا: "فیزیک - فیزیک دوازدهم")
                $prefix = $data['current_entry']['lesson_prefix'] ?? '';
                $data['current_entry']['lesson_name'] = $prefix . $lesson['name'];
                unset($data['current_entry']['lesson_prefix']); // پیشوند را پاک کن
                
                $this->fileHandler->saveData($this->chatId, $data);
                $this->fileHandler->saveState($this->chatId, 'awaiting_topic'); // برو به حالت پرسیدن مبحث

                // پیام دکمه‌ها را ویرایش می‌کنیم تا انتخاب نهایی نمایش داده شود
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $this->messageId,
                    "text" => "✅ درس انتخاب شده: " . htmlspecialchars($data['current_entry']['lesson_name']),
                    "reply_markup" => null // دکمه‌ها را حذف کن
                ]);

                // پیام جدید برای گرفتن مبحث ارسال می‌کنیم
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId, 
                    "text" => "عنوان/مبحث (مثلا گفتار ۱) را وارد کنید:"
                ]);
            }

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
            // ... (کد موجود)
            
            // --- تغییر کوچک: بجای ارسال پیام متنی، باید دکمه‌های اصلی را دوباره نشان دهیم ---
            // 1. ذخیره درس فعلی (کد موجود)
            $this->saveCurrentEntryToDb($data); 

            // 2. آماده شدن برای درس بعدی (کد موجود)
            $data['current_entry'] = []; 
            $this->fileHandler->saveData($this->chatId, $data); 
            
            // --- تغییر منطق ---
            // حالت را به 'awaiting_report_input' برمی‌گردانیم
            $this->fileHandler->saveState($this->chatId, 'awaiting_report_input'); 

            // دوباره دروس اصلی را می‌گیریم و نشان می‌دهیم
            $major = $this->db->getStudentMajor($this->chatId);
            $mainLessons = $this->db->getLessons(null, $major);
            $buttons = $this->buildLessonButtons($mainLessons);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId, 
                "text" => "➕ درس بعدی را انتخاب کنید:",
                "reply_markup" => json_encode(['inline_keyboard' => $buttons])
            ]);
            // --- پایان تغییر ---

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
