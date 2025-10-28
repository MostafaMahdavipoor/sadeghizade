<?php

namespace Bot;

use Exception;

trait Functions
{

    public function showMainMenu($isAdmin = false): void
    {

        $text = "✅ به ربات مشاوره کنکور خوش آمدید.\n\nلطفا یکی از گزینه‌های زیر را انتخاب کنید:";


        $buttons = [
            [
                ['text' => '✍️ ثبت گزارش روزانه', 'callback_data' => 'daily_report']
            ],
            [
                ['text' => '📅 برنامه مطالعاتی', 'callback_data' => 'view_study_plan'],
                ['text' => '📞 ارتباط با مشاور', 'callback_data' => 'contact_counselor']
            ]
        ];
        if ($isAdmin) {
            $buttons[] = [
                ['text' => '👮‍♂️ پنل ادمین', 'callback_data' => 'admin_panel']
            ];
        }

        $this->sendRequest("sendMessage", [
            "chat_id"      => $this->chatId,
            "text"         => $text,
            "reply_markup" => json_encode([
                "inline_keyboard" => $buttons
            ]),
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): void
    {
        $this->sendRequest("answerCallbackQuery", [
            "callback_query_id" => $callbackQueryId,
            "text" => $text,
            "show_alert" => $showAlert
        ]);
    }

    //   -------------------------------- توابع ثبت نام

    public function askMajor(): void
    {
        $buttons = [
            [['text' => 'تجربی', 'callback_data' => 'set_major_tajrobi']],
            [['text' => 'ریاضی', 'callback_data' => 'set_major_riazi']],
        ];
        $this->sendRequest("sendMessage", [
            "chat_id" => $this->chatId,
            "text" => "رشته تحصیلی خود را انتخاب کنید:",
            "reply_markup" => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    public function askGrade(): void
    {
        $buttons = [
            [['text' => 'دهم', 'callback_data' => 'set_grade_10']],
            [['text' => 'یازدهم', 'callback_data' => 'set_grade_11']],
            [['text' => 'دوازدهم', 'callback_data' => 'set_grade_12']],
        ];
        $this->sendRequest("sendMessage", [
            "chat_id" => $this->chatId,
            "text" => "مقطع تحصیلی خود را انتخاب کنید:",
            "reply_markup" => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    public function askReportTime(): void
    {
        $buttons = [
            [['text' => 'ساعت ۱۹', 'callback_data' => 'set_time_19:00:00']],
            [['text' => 'ساعت ۲۰', 'callback_data' => 'set_time_20:00:00']],
            [['text' => 'ساعت ۲۱', 'callback_data' => 'set_time_21:00:00']],
            [['text' => 'ساعت ۲۲', 'callback_data' => 'set_time_22:00:00']],
            [['text' => 'ساعت ۲۳', 'callback_data' => 'set_time_23:00:00']],
            [['text' => 'ساعت ۰۰', 'callback_data' => 'set_time_00:00:00']],
        ];
        $this->sendRequest("sendMessage", [
            "chat_id" => $this->chatId,
            "text" => "ساعتی که می‌خواهید گزارش فعالیت خود را ثبت کنید، انتخاب نمایید:",
            "reply_markup" => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    public function notifyAdminsOfRegistration(int $chatId, array $data): void
    {
        $studentInfo = $this->db->getUserInfo($chatId);
        $username = $studentInfo['username'] ? "@" . $studentInfo['username'] : "ندارد";

        // --- اصلاح شده: استفاده از HTML ---
        $text = "✅ <b>ثبت نام دانش آموز جدید</b>\n\n" .
            "<b>نام:</b> " . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . "\n" .
            "<b>نام کاربری:</b> " . $username . "\n" .
            "<b>رشته:</b> " . ($data['major'] == 'tajrobi' ? 'تجربی' : 'ریاضی') . "\n" .
            "<b>پایه:</b> " . htmlspecialchars($data['grade']) . "\n" .
            "<b>ساعت گزارش:</b> " . htmlspecialchars($data['time']) . "\n" .
            "<b>Chat ID:</b> <code>{$chatId}</code>";

        $admins = $this->db->getAdmins();
        foreach ($admins as $admin) {
            $this->sendRequest("sendMessage", [
                "chat_id" => $admin['chat_id'],
                "text" => $text,
                "parse_mode" => "HTML" // --- اصلاح شده ---
            ]);
        }
    }

    //   -------------------------------- توابع گزارش دهی

    public function askTestCount(): void
    {
        $buttons = [
            [['text' => '❌ تست نزدم', 'callback_data' => 'no_test']],
        ];
        $this->sendRequest("sendMessage", [
            "chat_id" => $this->chatId,
            "text" => "تعداد تست را وارد کنید:",
            "reply_markup" => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    /**
     * خلاصه درس وارد شده را نمایش می‌دهد
     */
    public function showEntrySummary(array $entryData): void
    {
        // --- اصلاح شده: استفاده از HTML ---
        $text = "<b>خلاصه درس ثبت شده:</b>\n\n" .
            "<b>درس:</b> " . htmlspecialchars($entryData['lesson_name']) . "\n" .
            "<b>مبحث:</b> " . htmlspecialchars($entryData['topic']) . "\n" .
            "<b>زمان مطالعه:</b> " . htmlspecialchars($entryData['study_time']) . " دقیقه\n" .
            "<b>تعداد تست:</b> " . htmlspecialchars($entryData['test_count']) . "\n\n" .
            "آیا می‌خواهید درس دیگری ثبت کنید؟";

        $buttons = [
            [['text' => '✅ اتمام گزارش', 'callback_data' => 'finish_report']],
            [['text' => '➕ ثبت درس بعدی', 'callback_data' => 'add_next_subject']],
        ];

        $this->sendRequest("sendMessage", [
            "chat_id" => $this->chatId,
            "text" => $text,
            "parse_mode" => "HTML", // (این از قبل درست بود)
            "reply_markup" => json_encode(['inline_keyboard' => $buttons])
        ]);
    }

    /**
     * اطلاعات فعلی (current_entry) را در دیتابیس ذخیره می‌کند
     */
    public function saveCurrentEntryToDb(array $stateData): bool
    {
        if (empty($stateData['report_id']) || empty($stateData['current_entry'])) {
            return false;
        }

        $entry = $stateData['current_entry'];

        return $this->db->addReportEntry(
            $stateData['report_id'],
            $entry['lesson_name'] ?? 'نا مشخص',
            $entry['topic'] ?? 'نا مشخص',
            $entry['study_time'] ?? 0,
            $entry['test_count'] ?? 0
        );
    }

    /**
     * گزارش کامل دانش آموز را برای ادمین‌ها ارسال می‌کند
     */
    public function notifyAdminsOfFullReport(int $reportId): void
    {
        $report = $this->db->getReportById($reportId);
        if (!$report) return;

        $entries = $this->db->getReportEntries($reportId);
        $student = $this->db->getStudent($report['chat_id']);
        $userInfo = $this->db->getUserInfo($report['chat_id']);
        $username = $userInfo['username'] ? "@" . $userInfo['username'] : "ندارد";
        $studentName = $student['first_name'] . ' ' . $student['last_name'];

        // --- اصلاح شده: استفاده از HTML ---
        $text = "✅ <b>گزارش ثبت شده توسط:</b> " . htmlspecialchars($studentName) . "\n" .
            "<b>نام کاربری:</b> " . $username . "\n" .
            "<b>تاریخ:</b> " . $report['report_date'] . "\n\n" .
            "------------------------------\n";

        if (empty($entries)) {
            $text .= "گزارشی ثبت نشده است (خطای احتمالی).";
        } else {
            foreach ($entries as $index => $entry) {
                $text .= "<b>" . ($index + 1) . ". درس:</b> " . htmlspecialchars($entry['lesson_name']) . "\n" .
                    "   <b>مبحث:</b> " . htmlspecialchars($entry['topic']) . "\n" .
                    "   <b>زمان:</b> " . $entry['study_time'] . " دقیقه\n" .
                    "   <b>تست:</b> " . $entry['test_count'] . " عدد\n" .
                    "------------------------------\n";
            }
        }

        $admins = $this->db->getAdmins();
        foreach ($admins as $admin) {
            $this->sendRequest("sendMessage", [
                "chat_id" => $admin['chat_id'],
                "text" => $text,
                "parse_mode" => "HTML" // (این از قبل درست بود)
            ]);
        }
    }

    /**
     * دلیل درس نخواندن را به ادمین‌ها اطلاع می‌دهد
     */
    public function notifyAdminsOfNoStudy(int $reportId): void
    {
        $report = $this->db->getReportById($reportId);
        if (!$report) return;

        $student = $this->db->getStudent($report['chat_id']);
        $userInfo = $this->db->getUserInfo($report['chat_id']);
        $username = $userInfo['username'] ? "@" . $userInfo['username'] : "ندارد";
        $studentName = $student['first_name'] . ' ' . $student['last_name'];

        // --- اصلاح شده: استفاده از HTML ---
        $text = "❌ <b>گزارش \"درس نخواندم\"</b>\n\n" .
            "<b>دانش آموز:</b> " . htmlspecialchars($studentName) . "\n" .
            "<b>نام کاربری:</b> " . $username . "\n" .
            "<b>تاریخ:</b> " . $report['report_date'] . "\n\n" .
            "<b>دلیل:</b>\n" . htmlspecialchars($report['reason']);

        $admins = $this->db->getAdmins();
        foreach ($admins as $admin) {
            $this->sendRequest("sendMessage", [
                "chat_id" => $admin['chat_id'],
                "text" => $text,
                "parse_mode" => "HTML" // (این از قبل درست بود)
            ]);

            // اگر عکس هم ارسال شده بود، عکس را هم بفرست
            if (!empty($report['reason_photo_id'])) {
                $this->sendRequest("sendPhoto", [
                    "chat_id" => $admin['chat_id'],
                    "photo" => $report['reason_photo_id'],
                    "caption" => "تصویر ضمیمه شده برای دلیل."
                ]);
            }
        }
    }
}