<?php
namespace Bot;

use DateTime;

trait HandleRequest
{

    public function handleRequest(): void
    {

        if (str_starts_with($this->text, "/start ")) {
            $token = substr($this->text, 7);

            if ($token === 'register') {

                $this->db->saveUser($this->message["from"], $token);

                $text = "به سامانه ثبت‌نام مشاوره کنکور خوش آمدید.\n\n" .
                    "برای شروع فرآیند ثبت‌نام، لطفاً روی دکمه زیر کلیک کنید:";

                $buttons = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ شروع ثبت نام', 'callback_data' => 'start_registration']
                        ]
                    ]
                ];

                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => $text,
                    "reply_markup" => json_encode($buttons)
                ]);

                return;
            }


            $sectionType = $sectionType ?? null;
            $this->db->saveUser($this->message["from"], $token);
        }

        $this->db->saveUser($this->message["from"]);
        $state       = $this->fileHandler->getState($this->chatId);
        $isAdmin     = $this->db->isAdmin($this->chatId);

        if ($this->text == "/start") {
            $this->fileHandler->saveState($this->chatId, null);
            $this->db->saveUser($this->message["from"]);


            $this->showMainMenu();


            return;
        }
    }
}
