<?php

namespace Bot;

use Exception;

trait Functions
{

    public function showMainMenu($isAdmin = false, $messaheId = null): void
    {

        $text = "✅ به ربات مشاوره کنکور خوش آمدید.\n\nلطفا یکی از گزینه‌های زیر را انتخاب کنید:";


        $buttons = [
            [
                ['text' => '✍️ ثبت گزارش روزانه', 'callback_data' => 'start_daily_report']
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
        if ($messaheId) {
            $this->sendRequest("editMessageText", [
                "chat_id"      => $this->chatId,
                "message_id" =>  $messaheId,
                "text"         => $text,
                "reply_markup" => json_encode([
                    "inline_keyboard" => $buttons
                ]),
            ]);
        } else {
            $this->sendRequest("sendMessage", [
                "chat_id"      => $this->chatId,
                "text"         => $text,
                "reply_markup" => json_encode([
                    "inline_keyboard" => $buttons
                ]),
            ]);
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): void
    {
        $this->sendRequest("answerCallbackQuery", [
            "callback_query_id" => $callbackQueryId,
            "text" => $text,
            "show_alert" => $showAlert
        ]);
    }

   
    public function notifyAdminsOfRegistration(int $chatId, array $data): void
    {
        $studentInfo = $this->db->getUserInfo($chatId);
        $username = $studentInfo['username'] ? "@" . $studentInfo['username'] : "ندارد";

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
private function formatWizardSummary(array $formData): string
    {
        // اگر هنوز دیتایی وارد نشده (مرحله اول)، خلاصه‌ای نشان نده
        if (empty($formData)) {
            return '';
        }

        $summaryText = "<b>اطلاعات وارد شده تاکنون:</b>\n";
        
        // مپ کردن کلیدهای انگلیسی به لیبل‌های فارسی
        $labelMap = [
            'first_name' => '🏷 نام',
            'last_name'  => '👤 نام خانوادگی',
            'major'      => '🔬 رشته',
            'grade'      => '🎓 پایه',
            'time'       => '⏰ ساعت گزارش'
        ];

        // مپ کردن مقادیر خاص (مثل tajrobi) به لیبل فارسی
        $valueMap = [
            'major' => [
                'tajrobi' => 'تجربی',
                'riazi'   => 'ریاضی'
            ],
            'grade' => [
                '10' => 'دهم',
                '11' => 'یازدهم',
                '12' => 'دوازدهم'
            ]
        ];

        // به ترتیب labelMap نمایش می‌دهیم تا مرتب باشد
        foreach ($labelMap as $key => $label) {
            if (isset($formData[$key])) {
                $value = $formData[$key];
                
                // اگر برای این کلید، مپِ مقدار وجود داشت، ترجمه‌اش کن
                if (isset($valueMap[$key]) && isset($valueMap[$key][$value])) {
                    $value = $valueMap[$key][$value];
                }
                
                $summaryText .= "{$label}: " . htmlspecialchars($value) . "\n";
            }
        }

        $summaryText .= "------------------------------\n";
        return $summaryText;
    }
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

    private function getRegistrationWizardConfig(): array
    {
        return [
            // مرحله 0: نام
            [
                'key' => 'first_name',
                'question' => '🎓 به سامانه ثبت‌نام خوش آمدید!
        لطفاً نام خود را وارد کنید:',
                'type' => 'text',
                'error_message' => 'خطا: لطفاً نام خود را به صورت متن وارد کنید.'
            ],
            // مرحله 1: نام خانوادگی
            [
                'key' => 'last_name',
                'question' => 'لطفاً نام خانوادگی خود را وارد کنید:',
                'type' => 'text',
                'error_message' => 'خطا: لطفاً نام خانوادگی خود را به صورت متن وارد کنید.'
            ],
            // مرحله 2: رشته
            [
                'key' => 'major',
                'question' => 'رشته تحصیلی خود را انتخاب کنید:',
                'type' => 'buttons',
                'options' => [
                    [['text' => 'تجربی', 'callback_data' => 'wizard_set_tajrobi']],
                    [['text' => 'ریاضی', 'callback_data' => 'wizard_set_riazi']],
                ],
                'value_map' => [ // callback_data را به مقداری که باید ذخیره شود مپ می‌کند
                    'wizard_set_tajrobi' => 'tajrobi',
                    'wizard_set_riazi' => 'riazi'
                ]
            ],
            // مرحله 3: پایه
            [
                'key' => 'grade',
                'question' => 'مقطع تحصیلی خود را انتخاب کنید:',
                'type' => 'buttons',
                'options' => [
                    [['text' => 'دهم', 'callback_data' => 'wizard_set_10']],
                    [['text' => 'یازدهم', 'callback_data' => 'wizard_set_11']],
                    [['text' => 'دوازدهم', 'callback_data' => 'wizard_set_12']],
                ],
                'value_map' => [
                    'wizard_set_10' => '10',
                    'wizard_set_11' => '11',
                    'wizard_set_12' => '12'
                ]
            ],
            // مرحله 4: ساعت گزارش
            [
                'key' => 'time',
                'question' => 'ساعتی که می‌خواهید گزارش فعالیت خود را ثبت کنید، انتخاب نمایید:',
                'type' => 'buttons',
                'options' => [
                    [['text' => 'ساعت ۱۹', 'callback_data' => 'wizard_set_19:00:00']],
                    [['text' => 'ساعت ۲۰', 'callback_data' => 'wizard_set_20:00:00']],
                    [['text' => 'ساعت ۲۱', 'callback_data' => 'wizard_set_21:00:00']],
                    [['text' => 'ساعت ۲۲', 'callback_data' => 'wizard_set_22:00:00']],
                    [['text' => 'ساعت ۲۳', 'callback_data' => 'wizard_set_23:00:00']],
                    [['text' => 'ساعت ۰۰', 'callback_data' => 'wizard_set_00:00:00']],
                ],
                'value_map' => [
                    'wizard_set_19:00:00' => '19:00:00',
                    'wizard_set_20:00:00' => '20:00:00',
                    'wizard_set_21:00:00' => '21:00:00',
                    'wizard_set_22:00:00' => '22:00:00',
                    'wizard_set_23:00:00' => '23:00:00',
                    'wizard_set_00:00:00' => '00:00:00'
                ]
            ]
        ];
    }

    public function processWizard(mixed $inputValue, bool $isCallback, ?int $messageId): void
    {
        $data = $this->fileHandler->getData($this->chatId);
        $config = $this->getRegistrationWizardConfig(); // در آینده می‌توانید نام ویزارد را داینامیک کنید

        // اگر ویزاردی فعال نیست، خارج شو
        if (!isset($data['wizard']) || $data['wizard'] !== 'registration') {
            return;
        }

        $currentStep = (int)$data['step']; // مرحله‌ای که کاربر *در آن قرار دارد*
        $formData = $data['form_data'] ?? [];

        // --- 1. پردازش ورودی ---
        $isValid = true;
        $valueToSave = null;

        if ($inputValue === 'wizard_cancel') {
            $this->fileHandler->saveData($this->chatId, []); // پاک کردن داده
            $this->fileHandler->saveState($this->chatId, null); // پاک کردن حالت
            $this->fileHandler->saveMessageId($this->chatId, null); // پاک کردن آیدی پیام
            $this->showMainMenu($this->db->isAdmin($this->chatId), $messageId);
            return;
        }

        if ($inputValue === 'wizard_back') {
            if ($currentStep > 0) {
                $currentStep--; // برگرد به مرحله قبل
            }
        }
        // اگر ورودی از مرحله قبلی آمده است (نه اولین نمایش یا بازگشت)
        elseif ($currentStep >= 0 && $inputValue !== null) {
            $stepConfig = $config[$currentStep]; // کانفیگ مرحله‌ای که *تمام شد*

            if ($isCallback) {
                // ورودی دکمه است
                if ($stepConfig['type'] === 'buttons' && isset($stepConfig['value_map'][$inputValue])) {
                    $valueToSave = $stepConfig['value_map'][$inputValue];
                } else {
                    $isValid = false; // دکمه نامعتبر
                }
            } else {
                // ورودی متن است
                if ($stepConfig['type'] === 'text') {
                    $valueToSave = $inputValue;
                } else {
                    $isValid = false; // منتظر دکمه بودیم، متن دریافت شد
                }
            }

            if ($isValid) {
                $formData[$stepConfig['key']] = $valueToSave;
                $currentStep++; // برو به مرحله بعد
            } else {
                // ورودی نامعتبر بود، همان مرحله را دوباره نمایش بده با پیام خطا
                if ($stepConfig['type'] === 'text' && isset($stepConfig['error_message'])) {
                    // (بهتر است پیام خطا در یک پیام جدید ارسال شود تا کاربر گیج نشود)
                    $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => $stepConfig['error_message']]);
                }
                // (برای دکمه‌ها خطایی ارسال نمی‌کنیم چون کاربر نمی‌تواند اشتباه کلیک کند)
            }
        }
        // اگر اولین بار است (inputValue == null)
        elseif ($inputValue === null && $currentStep === -1) {
            $currentStep = 0; // برو به مرحله اول
        }


        // --- 2. ذخیره حالت جدید ---
        $data['step'] = $currentStep;
        $data['form_data'] = $formData;
        $this->fileHandler->saveData($this->chatId, $data);

        // --- 3. نمایش مرحله بعدی یا اتمام ---

        // اگر مراحل تمام شده‌اند
        if ($currentStep >= count($config)) {
            $this->finishRegistration($formData, $messageId);
        } else {
            // نمایش مرحله فعلی
            $this->askWizardStep($config[$currentStep], $data, $messageId);
        }
    }

    private function askWizardStep(array $stepConfig, array $data, ?int $messageId): void
    {
        $summary = $this->formatWizardSummary($data['form_data'] ?? []);

        // 2. متن سوال فعلی را به آن بچسبان
        $text = $summary . $stepConfig['question'];
        $buttons = [];

        if ($stepConfig['type'] === 'buttons') {
            $buttons = $stepConfig['options'];
        }

        // اضافه کردن دکمه‌های "بازگشت" و "انصراف"
        $navigationButtons = [];
        $navigationButtons[] = ['text' => '❌ انصراف', 'callback_data' => 'wizard_cancel'];
        if ($data['step'] > 0) { // دکمه بازگشت برای مرحله اول (step 0) نمایش داده نشود
            $navigationButtons[] = ['text' => '🔙 بازگشت', 'callback_data' => 'wizard_back'];
        }
        $buttons[] = $navigationButtons;

        $params = [
            "chat_id"      => $this->chatId,
            "text"         => $text,
            "parse_mode"   => "HTML", 
            "reply_markup" => json_encode(["inline_keyboard" => $buttons]),
        ];

        // اگر $messageId وجود داشت (یعنی یا مرحله اول بود یا مرحله قبلی دکمه‌ای بود)، پیام را ویرایش کن
        if ($messageId) {
            $params["message_id"] = $messageId;
            $this->sendRequest("editMessageText", $params);
        } else {
            // این فقط برای مرحله اول (که با /start register شروع شده) رخ می‌دهد
            $res = $this->sendRequest("sendMessage", $params);
            // آیدی پیام اصلی ویزارد را ذخیره می‌کنیم تا در مراحل بعدی آن را ویرایش کنیم
            if (isset($res['result']['message_id'])) {
                $this->fileHandler->saveMessageId($this->chatId, $res['result']['message_id']);
            }
        }
    }


   private function finishRegistration(array $formData, ?int $messageId): void
    {
        $this->db->finalizeStudentRegistration(
            $this->chatId,
            $formData['first_name'] ?? 'ناشناس',
            $formData['last_name'] ?? '',
            $formData['major'],
            $formData['grade'],
            $formData['time']
        );

        $this->fileHandler->saveState($this->chatId, null);
        $this->fileHandler->saveData($this->chatId, []); 
        $this->fileHandler->saveMessageId($this->chatId, null); 
        $summary = $this->formatWizardSummary($formData); 
        
        $text = "✅ <b>ثبت نام شما با موفقیت تکمیل شد.</b>\n\n" .
                "اطلاعات شما در سامانه ثبت گردید. می‌توانید با استفاده از دکمه زیر به منوی اصلی بازگردید.\n\n" .
                $summary; 

        $buttons = [
            [['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'go_to_main_menu']]
        ];

        if ($messageId) {
            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" =>  $messageId,
                "text" => $text,
                "parse_mode" => "HTML", 
                "reply_markup" => json_encode(['inline_keyboard' => $buttons])
            ]);
        } else {
            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId, 
                "text" => $text,
                "parse_mode" => "HTML", 
                "reply_markup" => json_encode(['inline_keyboard' => $buttons])
            ]);
        }

        $this->notifyAdminsOfRegistration($this->chatId, $formData);
    }
}
