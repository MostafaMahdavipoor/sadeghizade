<?php

namespace Bot;

use DateTime;

trait HandleRequest
{

    public function handleRequest(): void
    {
        $token = null;

        if (str_starts_with($this->text, "/start ")) {
            $token = substr($this->text, 7);
        }

        $this->db->saveUser($this->message["from"], $token);
        $isAdmin = $this->db->isAdmin($this->chatId);

        $state = $this->fileHandler->getState($this->chatId);
        $data = $this->fileHandler->getData($this->chatId);


        if (isset($this->message['photo']) && $state === 'awaiting_no_study_reason') {
            $photoId = $this->message['photo'][count($this->message['photo']) - 1]['file_id'];
            $report = $this->db->getTodaysReport($this->chatId);

            if ($report) {
                $reasonText = $this->message['caption'] ?? 'تصویر ارسال شد';
                $this->db->updateReportReason($report['report_id'], $reasonText, $photoId);

                $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "دلیل شما (به همراه عکس) ثبت شد."]);
                $this->fileHandler->saveState($this->chatId, null); // پاک کردن حالت
                $this->notifyAdminsOfNoStudy($report['report_id']); // اطلاع به ادمین
            }
            return;
        }

        if (empty($this->text)) {
            return;
        }

        if ($token === 'register') {

            $student = $this->db->getStudent($this->chatId);

            if ($student && $student['status'] === 'active') {
                $res =  $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "شما قبلاً ثبت‌نام کرده‌اید و دانش‌آموز فعال هستید. نیازی به ثبت‌نام مجدد نیست.",
                ]);
                sleep(2);

                $this->showMainMenu($isAdmin, $res['result']['message_id'] ?? null);
                return;
            }
            $this->db->createStudent($this->chatId);

            $this->fileHandler->saveState($this->chatId, 'awaiting_first_name');
            $this->fileHandler->saveData($this->chatId, []);
            $buttons = [
                [['text' => '❌ انصراف', 'callback_data' => 'cancell']],
            ];
            $res = $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "🎓 به سامانه ثبت‌نام مشاوره کنکور خوش آمدید!\n\n" .
                    "برای شروع، لطفاً نام  خود را وارد کنید(فقط نام):",
                "reply_markup" => json_encode(['inline_keyboard' => $buttons])
            ]);
            $this->fileHandler->saveMessageId($this->chatId, $res['result']['message_id'] ?? null);
            return;
        }

        if ($this->text == "/start") {
            $this->fileHandler->saveState($this->chatId, null); // اصلاح شد: خروج از هر حالتی
            $this->showMainMenu($isAdmin);
            return;
        }

        if ($state) {
            $this->deleteMessageWithDelay();
            $messaheId = $this->fileHandler->getMessageId($this->chatId);
             $buttons = [
                [['text' => '❌ انصراف', 'callback_data' => 'cancell']],
            ];
            
            switch ($state) {
                case 'awaiting_first_name':
                    $data['first_name'] = $this->text;
                    $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
                    $this->fileHandler->saveState($this->chatId, 'awaiting_last_name'); // اصلاح شد
                    $this->sendRequest(
                        "editMessageText",
                        [
                            "chat_id" => $this->chatId,
                            "message_id" =>  $messaheId,
                            "text" => "لطفاً نام خانوادگی خود را وارد کنید:",
                            "reply_markup" => json_encode(['inline_keyboard' => $buttons])
                        ]
                    );
                    break;

                case 'awaiting_last_name':
                    $data['last_name'] = $this->text;
                    $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
                    $this->fileHandler->saveState($this->chatId, 'awaiting_major'); // اصلاح شد
                    $this->askMajor($messaheId); // تابع کمکی از Functions.php
                    break;

                // --- مراحل گزارش دهی ---
                case 'awaiting_no_study_reason':
                    $report = $this->db->getTodaysReport($this->chatId);
                    if ($report) {
                        $this->db->updateReportReason($report['report_id'], $this->text, null);
                        $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "دلیل شما ثبت شد."]);
                        $this->fileHandler->saveState($this->chatId, null); // اصلاح شد
                        $this->notifyAdminsOfNoStudy($report['report_id']); // اطلاع به ادمین
                    }
                    break;

                case 'awaiting_lesson_name':
                    $data['current_entry'] = ['lesson_name' => $this->text];
                    $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
                    $this->fileHandler->saveState($this->chatId, 'awaiting_topic'); // اصلاح شد
                    $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "عنوان/مبحث (مثلا گفتار ۱) را وارد کنید:"]);
                    break;

                case 'awaiting_topic':
                    $data['current_entry']['topic'] = $this->text;
                    $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
                    $this->fileHandler->saveState($this->chatId, 'awaiting_study_time'); // اصلاح شد
                    $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "زمان مطالعه (به دقیقه) را وارد کنید:"]);
                    break;

                case 'awaiting_study_time':
                    if (!is_numeric($this->text)) {
                        $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "لطفا فقط عدد وارد کنید. (زمان مطالعه به دقیقه):"]);
                        return; // حالت را تغییر نده
                    }
                    $data['current_entry']['study_time'] = (int)$this->text;
                    $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
                    $this->fileHandler->saveState($this->chatId, 'awaiting_test_count'); // اصلاح شد
                    $this->askTestCount(); // تابع کمکی برای ارسال دکمه "تست نزدم"
                    break;

                case 'awaiting_test_count':
                    if (!is_numeric($this->text)) {
                        $this->sendRequest("sendMessage", ["chat_id" => $this->chatId, "text" => "لطفا فقط عدد وارد کنید. (تعداد تست):"]);
                        return; // حالت را تغییر نده
                    }
                    $data['current_entry']['test_count'] = (int)$this->text;
                    $this->fileHandler->saveData($this->chatId, $data); // اصلاح شد
                    $this->fileHandler->saveState($this->chatId, 'awaiting_report_decision'); // اصلاح شد
                    $this->showEntrySummary($data['current_entry']); // نمایش خلاصه و دکمه‌های "اتمام" و "درس بعدی"
                    break;
            }
            return; // چون در یک حالت خاص بوده، ادامه نده
        }
    }
}
