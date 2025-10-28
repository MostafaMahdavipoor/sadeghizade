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
}
