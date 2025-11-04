<?php
/*test*/

namespace Bot;

use Config\AppConfig;
use Payment\ZarinpalPaymentHandler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use CURLFile;
use morilog\Jalali\Jalalian;

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
        if ($callbackData === 'admin_panel') {
            $this->handleAdminPanel($callbackQueryId);
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
            if ($report['status'] === 'pending') {
                $this->db->deleteReport($report['report_id']);
                $newReportId = $this->db->createDailyReport(
                    $this->chatId,
                    date('Y-m-d'),
                    date('Y-m-d H:i:s') 
                );

                if ($newReportId) {
                    $report['report_id'] = $newReportId;
                    $this->answerCallbackQuery($callbackQueryId, "می‌توانید گزارش جدید را شروع کنید.");
                } else {
                    $this->answerCallbackQuery($callbackQueryId, "خطا در گزارش قبلی یا ایجاد گزارش جدید.", true);
                    return;
                }
            } elseif ($report['status'] === 'submitted' || $report['status'] === 'reason_provided') {
                $this->answerCallbackQuery($callbackQueryId, "شما قبلاً گزارش امروز را ثبت کرده‌اید.", true);
                return;
            }
            $major = $this->db->getStudentMajor($this->chatId);
            if (!$major) {
                $this->answerCallbackQuery($callbackQueryId, "خطا: اطلاعات رشته شما یافت نشد. برای ثبت‌نام مجدد /start register را بزنید.", true);
                return;
            }

            $mainLessons = $this->db->getLessons(null, $major);
            if (empty($mainLessons)) {
                $this->answerCallbackQuery($callbackQueryId, "خطا: درسی برای رشته شما در سیستم تعریف نشده است.", true);
                return;
            }

            $this->fileHandler->saveData($this->chatId, [
                'report_id' => $report['report_id'],
                'current_entry' => []
            ]);

            $this->fileHandler->saveState($this->chatId, 'awaiting_report_input');

            $buttons = $this->buildLessonButtons($mainLessons);

            $buttons[] = [
                ['text' => '« بازگشت', 'callback_data' => 'go_to_main_menu']
            ];
            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $this->messageId,
                "text" => "✍️ لطفا درس  را  که مطالعه کردید را انتخاب کنید:",
                "reply_markup" => json_encode(['inline_keyboard' => $buttons])
            ]);
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }
        if ($callbackData === 'admin_students') {
            $this->handleAdminStudentsList($callbackQueryId);
            return;
        }
        if (str_starts_with($callbackData, 'admin_view_student_')) {
            $this->handleAdminViewStudent($callbackQueryId, $callbackData);
            return;
        }
        if (str_starts_with($callbackData, 'admin_export_student_')) {
            $this->handleAdminExportStudent($callbackQueryId, $callbackData);
            return;
        }
        if (str_starts_with($callbackData, 'select_lesson_')) {
            if ($state !== 'awaiting_report_input') {
                $this->answerCallbackQuery($callbackQueryId);
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

                $data['current_entry']['lesson_prefix'] = ($data['current_entry']['lesson_prefix'] ?? '') . $lesson['name'] . " - ";
                $this->fileHandler->saveData($this->chatId, $data);

                $backButton = [['text' => '« بازگشت', 'callback_data' => 'start_daily_report']];
                $buttons = $this->buildLessonButtons($subLessons, 2, $backButton);

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $this->messageId,
                    "text" => " '" . htmlspecialchars($lesson['name']) . "' را انتخاب کنید:",
                    "reply_markup" => json_encode(['inline_keyboard' => $buttons])
                ]);
            } else {
                $prefix = $data['current_entry']['lesson_prefix'] ?? '';
                $lessonName = $prefix . $lesson['name'];

                $data['current_entry']['lesson_name'] = $lessonName;
                unset($data['current_entry']['lesson_prefix']);
                $this->fileHandler->saveData($this->chatId, $data);
                $this->fileHandler->saveState($this->chatId, 'awaiting_topic');

                $backButtonKeyboard = json_encode(['inline_keyboard' => [
                    [['text' => '« بازگشت', 'callback_data' => 'start_daily_report']],
                ]]);

                $summaryText = "✅ <b>درس انتخاب شده:</b>\n " . htmlspecialchars($lessonName) . "\n\n";

                $questionText = "لطفا <b>عنوان یا مبحث</b> مطالعه شده را وارد کنید:";
                $text = $summaryText . $questionText;

                $res =  $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "text" => $text,
                    "message_id" => $this->messageId,
                    "parse_mode" => "HTML",
                    "reply_markup" => $backButtonKeyboard
                ]);

                $this->fileHandler->saveMessageId($this->chatId, $res['result']['message_id'] ?? $this->messageId);
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
            $this->fileHandler->saveState($this->chatId, 'awaiting_no_study_reason');
            $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "لطفا دلیل درس نخواندن خود را (متن یا عکس) ارسال کنید:"]);
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }

        if ($callbackData === 'no_test') {
            if ($state !== 'awaiting_test_count') {
                $this->answerCallbackQuery($callbackQueryId);
                return;
            }
            $data['current_entry']['test_count'] = 0;
            $this->fileHandler->saveData($this->chatId, $data);
            $this->saveCurrentEntryToDb($data);
            $this->fileHandler->saveState($this->chatId, 'awaiting_report_decision');
            $this->showEntrySummary($data['report_id'], $this->messageId);
            $this->answerCallbackQuery($callbackQueryId, "تست نزدم ثبت شد.");
            return;
        }

        if ($callbackData === 'add_next_subject') {
            $data['current_entry'] = [];
            $this->fileHandler->saveData($this->chatId, $data);
            $this->fileHandler->saveState($this->chatId, 'awaiting_report_input');
            $major = $this->db->getStudentMajor($this->chatId);
            $mainLessons = $this->db->getLessons(null, $major);
            $buttons = $this->buildLessonButtons($mainLessons);

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $this->messageId,
                "text" => "➕ درس بعدی را انتخاب کنید:",
                "reply_markup" => json_encode(['inline_keyboard' => $buttons])
            ]);

            $this->answerCallbackQuery($callbackQueryId);
            return;
        }
        if ($callbackData === 'contact_counselor') {
            $this->showCounselorContact($messageId);
            $this->answerCallbackQuery($callbackQueryId);
            return;
        }
        if ($callbackData === 'finish_report') {
            if ($state !== 'awaiting_report_decision') {
                $this->answerCallbackQuery($callbackQueryId);
                return;
            }

            // $this->saveCurrentEntryToDb($data);

            $reportId = $data['report_id'];
            $this->db->updateReportStatus($reportId, 'submitted');
            $this->fileHandler->saveState($this->chatId, null);


            $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "گزارش شما با موفقیت ثبت شد. ممنون!"]);
            $this->answerCallbackQuery($callbackQueryId, "گزارش ثبت شد.");

            $this->notifyAdminsOfFullReport($reportId);
            return;
        }

        $this->answerCallbackQuery($callbackQueryId);
    }

   public function sendRequest($method, $data)
    {
        $url = "https://api.telegram.org/bot" . $this->botToken . "/$method";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // تشخیص اینکه آیا این یک درخواست آپلود فایل است (مثل sendDocument، sendPhoto)
        $isFileUpload = false;
        // روش دقیق‌تر: بررسی وجود نمونه‌ای از CURLFile
        foreach ($data as $value) {
            if ($value instanceof CURLFile) {
                $isFileUpload = true;
                break;
            }
        }

        if ($isFileUpload) {
            // برای آپلود فایل، از آرایه خام به عنوان POSTFIELDS استفاده می‌کنیم.
            // cURL به طور خودکار آن را به multipart/form-data تبدیل می‌کند.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            // توجه: هدر Content-Type: application/json نباید تنظیم شود
        } else {
            // برای درخواست‌های معمولی (بدون فایل)، از JSON استفاده می‌کنیم.
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

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
            // در صورت بروز خطا در ارسال، لاگ‌های دقیق‌تری بنویسید
            error_log("❌ Telegram API Error ($method, HTTP $httpCode): " . $errorMessage);
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
        $logMessage = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    private function handleAdminPanel($callbackQueryId)
    {
        if (!$this->db->isAdmin($this->chatId)) {
            $this->answerCallbackQuery($callbackQueryId, "شما دسترسی ادمین ندارید.", true);
            return;
        }

        $this->showAdminPanel($this->messageId);
        $this->answerCallbackQuery($callbackQueryId);
    }

    /**
     * کالبک دکمه 'admin_export_student_' را مدیریت می‌کند
     * یک فایل اکسل (با تاریخ شمسی) از گزارش‌های دانش‌آموز ساخته و ارسال می‌کند
     */
    private function handleAdminExportStudent($callbackQueryId, $callbackData)
    {
        $studentChatId = (int)substr($callbackData, strlen('admin_export_student_'));
        if ($studentChatId <= 0) {
            $this->answerCallbackQuery($callbackQueryId, "خطا در یافتن دانش‌آموز.", true);
            return;
        }

        $this->answerCallbackQuery($callbackQueryId, "⏳ در حال آماده‌سازی فایل اکسل... لطفاً کمی صبر کنید.", false);

        try {
            $student = $this->db->getStudent($studentChatId);
            $reportData = $this->db->getStudentDetailedReportData($studentChatId);

            if (!$student || empty($reportData)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ هیچ گزارش ثبت‌شده‌ای (submitted) برای این دانش‌آموز یافت نشد تا خروجی اکسل تهیه شود."
                ]);
                return;
            }


            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setRightToLeft(true);
            $sheet->setTitle('گزارش دانش‌آموز');

            $sheet->setCellValue('A1', 'تاریخ');
            $sheet->setCellValue('B1', 'درس');
            $sheet->setCellValue('C1', 'مبحث');
            $sheet->setCellValue('D1', 'زمان مطالعه (دقیقه)');
            $sheet->setCellValue('E1', 'تعداد تست');
            $sheet->getStyle('A1:E1')->getFont()->setBold(true);

            $row = 2;
            foreach ($reportData as $entry) {


                $jalaliDate = Jalalian::fromFormat('Y-m-d', $entry['report_date'])->format('Y/m/d');


                $sheet->setCellValue('A' . $row, $jalaliDate);
                $sheet->setCellValue('B' . $row, $entry['lesson_name']);
                $sheet->setCellValue('C' . $row, $entry['topic']);
                $sheet->setCellValue('D' . $row, $entry['study_time']);
                $sheet->setCellValue('E' . $row, $entry['test_count']);
                $row++;
            }


            foreach (range('A', 'E') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $studentName = preg_replace('/[^A-Za-z0-9_]/', '', $student['first_name'] . '_' . $student['last_name']);
            $fileName = "report_{$studentName}_{$studentChatId}_" . time() . ".xlsx";

            $filePath = dirname(__DIR__, 2) . '/temp/' . $fileName;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            $caption = "خروجی اکسل گزارش‌های دانش‌آموز (شمسی):\n" . $student['first_name'] . ' ' . $student['last_name'];

            $this->sendRequest('sendDocument', [
                'chat_id' => $this->chatId,
                'document' => new \CURLFile(realpath($filePath)),
                'caption' => $caption,
                'reply_to_message_id' => $this->messageId
            ]);

            unlink($filePath);
        } catch (\Exception $e) {
            error_log("خطا در ساخت اکسل شمسی: " . $e->getMessage());
            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "❌ بروز خطا در هنگام ساخت فایل اکسل. لطفا لاگ سرور را بررسی کنید."
            ]);
        }
    }
}
