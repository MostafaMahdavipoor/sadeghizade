<?php
namespace Bot;

use DateTime;

trait HandleRequest
{

    public function handleRequest(): void
    {

        $user           = $this->db->getUserByChatId($this->chatId);
        $state          = $this->fileHandler->getState($this->chatId);
        $isStartCommand = str_starts_with($this->text, "/start");
        $lang           = $this->fileHandler->getUserLanguage($this->chatId);


        if (str_starts_with($this->text, "/start ")) {
            $token = substr($this->text, 7);

            if (str_contains($token, '__bc')) {
                $payloadParts  = explode('__', $token, 2);
                $originalToken = $payloadParts[0];
                $trackingToken = $payloadParts[1] ?? '';
                if (str_starts_with($trackingToken, 'bc')) {
                    preg_match('/^bc(\d+)_/', $trackingToken, $matches);
                    if (isset($matches[1])) {
                        $broadcastId = (int) $matches[1];
                        $this->db->logBroadcastClick($broadcastId, $this->chatId);
                    }
                }
                $token = $originalToken;
            }

            if (str_starts_with($token, "view_attachments_")) {
                $response = $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای ایجاد پروژه جدید، لطفاً روی دکمه زیر کلیک کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "ایجاد پروژه ➕", "url" => "https://t.me/Arcrambot?start=create_project"]],
                        ],
                    ]),
                ]);
            }
            if (str_starts_with($token, "NetFreedom")) {
                $text = "🚀 *فیلترشکن IP ثابت و پرسرعت می‌خوای؟*\n\n"
                    . "✅ مناسب برای ترید، گیمینگ، اینستاگرام و کارهای فریلنسری.\n\n"
                    . "✨ روی دکمه «اکانت تست» کلیک کن و کیفیت رو خودت ببین\n\n"
                    . "🎁 *هدیه ویژه کاربران ⚜️Ram Ai 🏛🏗⚜️:*\n"
                    . "کد تخفیف `off10` برای اولین خرید شما فعاله\n\n"
                    . "👇 برای شروع، دکمه زیر رو بزن:";

                $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => $text,
                    "parse_mode"   => "Markdown",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "✅ دریافت آنی سرویس", "url" => "https://t.me/VPNNetFreedom_bot?start=start"]],
                        ],
                    ]),
                ]);
                return;
            }
            if ($token === 'start_consultation') {
                $lang = $this->fileHandler->getUserLanguage($this->chatId);

                if ($lang === 'fa') {
                    $text = "🧑‍💼 <b>بخش مشاوره</b>\n"
                        . "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
                    $buttons = [
                        [
                            ["text" => "🕑 رزرو تایم مشاوره", "callback_data" => "career_consult_menu"],
                        ],
                    ];
                } else {
                    $text = "🧑‍💼 <b>Professional Section</b>\n"
                        . "Please choose one of the options below:";
                    $buttons = [
                        [
                            ["text" => "🕑 Book Consultation Time", "callback_data" => "career_consult_menu"],
                        ],
                    ];
                }

                $payload = [
                    "chat_id"      => $this->chatId,
                    "text"         => $text,
                    "parse_mode"   => "HTML",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => $buttons,
                    ], JSON_UNESCAPED_UNICODE),
                ];
                $this->sendRequest("sendMessage", $payload);
                return;
            }

            if (str_starts_with($token, "language")) {
                $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => "Please choose your language:\nلطفاً زبان خود را انتخاب کنید:",
                    "reply_markup" => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🇮🇷 فارسی', 'callback_data' => 'set_lang_fa'],
                                ['text' => '🇬🇧 English', 'callback_data' => 'set_lang_en'],
                            ],
                        ],
                    ]),
                ]);
                return;
            }
            if (str_starts_with($token, "broadcast")) {
                $raw = substr($token, 9);

                if (str_contains($raw, '_')) {
                    [$broadcastIdStr, $cleanToken] = explode('_', $raw, 2);

                    if (is_numeric($broadcastIdStr)) {
                        $broadcastId = (int) $broadcastIdStr;

                        $this->db->addButtonClick($broadcastId, (int) $this->chatId);

                        error_log("✔️ Broadcast token parsed | broadcast_id: {$broadcastId} | token: {$cleanToken}");

                        $token = $cleanToken;
                    } else {
                        error_log("❌ Invalid broadcast ID format: {$broadcastIdStr}");
                        return;
                    }
                } else {
                    error_log("❌ Invalid broadcast token format (missing _): {$raw}");
                    return;
                }
            }

            if ($token === "create_portfolio") {
                $freelancer = $this->db->getFreelancerByChatId($this->chatId);

                if (! $freelancer) {
                    $response = $this->sendRequest("sendMessage", [
                        "chat_id"      => $this->chatId,
                        "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای ثبت‌نام به عنوان فریلنسر، لطفاً روی دکمه زیر کلیک کنید:",
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [["text" => "ورود به ربات پروژه 🚀", "url" => "https://t.me/Arcrambot?start=freelance"]],
                            ],
                        ]),
                    ]);
                    $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
                    return;
                }

                if ($this->db->userHasPortfolio($this->chatId)) {
                    $response = $this->sendRequest("sendMessage", [
                        "chat_id"      => $this->chatId,
                        "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای مشاهده یا ویرایش پورتفولیو، لطفاً روی دکمه زیر کلیک کنید:",
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [["text" => "ورود به ربات پروژه 🚀", "url" => "https://t.me/Arcrambot?start=freelance"]],
                            ],
                        ]),
                    ]);
                    return;
                }

                $response = $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای آپلود پورتفولیو، لطفاً روی دکمه زیر کلیک کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "ورود به ربات پروژه 🚀", "url" => "https://t.me/Arcrambot?start=freelance"]],
                        ],
                    ]),
                ]);
                if (isset($response['result']['message_id'])) {
                    $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
                }
                return;
            }

            if (str_starts_with($token, "view_portfolio_")) {
                $chatId = str_replace("view_portfolio_", "", $token);

                $portfolioItems = $this->db->getUserPortfolioItems($chatId);

                if (empty($portfolioItems)) {
                    $this->sendRequest("sendMessage", [
                        "chat_id" => $this->chatId,
                        "text"    => "❌ این کاربر هنوز نمونه کاری ثبت نکرده است.",
                    ]);
                    return;
                }

                foreach ($portfolioItems as $item) {
                    $caption = $item['caption'] ?? '';
                    if ($item['type'] === 'photo') {
                        $this->sendRequest("sendPhoto", [
                            "chat_id"    => $this->chatId,
                            "photo"      => $item['content'],
                            "caption"    => $caption,
                            "parse_mode" => "HTML",
                        ]);
                    } elseif ($item['type'] === 'video') {
                        $this->sendRequest("sendVideo", [
                            "chat_id"    => $this->chatId,
                            "video"      => $item['content'],
                            "caption"    => $caption,
                            "parse_mode" => "HTML",
                        ]);
                    } elseif ($item['type'] === 'document') {
                        $this->sendRequest("sendDocument", [
                            "chat_id"    => $this->chatId,
                            "document"   => $item['content'],
                            "caption"    => $caption,
                            "parse_mode" => "HTML",
                        ]);
                    } elseif ($item['type'] === 'text') {
                        $this->sendRequest("sendMessage", [
                            "chat_id" => $this->chatId,
                            "text"    => $caption,
                        ]);
                    }
                }
                return;
            }

            if (str_contains($token, "freelancer")) {
                $this->db->saveUser($this->message["from"]);
                if ($this->db->freelancerProfileExists($this->chatId)) {
                    $this->sendRequest("sendMessage", [
                        "chat_id"      => $this->chatId,
                        "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای ویرایش اطلاعات فریلنسر، لطفاً روی دکمه زیر کلیک کنید:",
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [["text" => "ورود به ربات پروژه 🚀", "url" => "https://t.me/Arcrambot?start=freelance"]],
                            ],
                        ]),
                    ]);
                    return;
                }

                $response = $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای ادامه، لطفاً روی دکمه زیر کلیک کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "ورود به ربات پروژه 🚀", "url" => "https://t.me/Arcrambot?start=freelance"]],
                        ],
                    ]),
                ]);

                if (isset($response['result']['message_id'])) {
                    $this->fileHandler->saveMessageId($this->chatId, $response['result']['message_id']);
                } else {
                    error_log("Error: No message_id received in Telegram response.");
                }

                return;
            }

            if (str_contains($token, "create_project")) {
                $response = $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای ایجاد پروژه جدید، لطفاً روی دکمه زیر کلیک کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "ایجاد پروژه ➕", "url" => "https://t.me/Arcrambot?start=create_project"]],
                        ],
                    ]),
                ]);

                if (! isset($response['ok']) || ! $response['ok']) {
                    error_log("Error: Telegram did not return success for create_project.");
                }
                return;
            }

            if (str_starts_with($token, "apply_project_")) {
                $response = $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => "📢 بخش پروژه به ربات دیگری منتقل شده است. برای ایجاد پروژه جدید، لطفاً روی دکمه زیر کلیک کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "ایجاد پروژه ➕", "url" => "https://t.me/Arcrambot?start=create_project"]],
                        ],
                    ]),
                ]);
            }

            $sectionType = $sectionType ?? null;
            $this->handleStartToken($token);
            $this->db->saveUser($this->message["from"], $token);
            $this->db->saveOrUpdateReferralLink($token, $sectionType);
        }

        $this->db->saveUser($this->message["from"]);
        $state       = $this->fileHandler->getState($this->chatId);
        $isAdmin     = $this->db->isAdmin($this->chatId);

        if ($this->text == "/start") {
            $this->fileHandler->saveState($this->chatId, null);
            $this->db->saveUser($this->message["from"]);
            $isAdmin  = $this->db->isAdmin($this->chatId);

            $userLang = $this->db->getBotLanguage($this->chatId);

            if ($userLang === null) {
                $this->sendRequest("sendMessage", [
                    "chat_id"      => $this->chatId,
                    "text"         => "Please choose your language:\nلطفاً زبان خود را انتخاب کنید:",
                    "reply_markup" => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🇮🇷 فارسی', 'callback_data' => 'set_lang_fa'],
                                ['text' => '🇬🇧 English', 'callback_data' => 'set_lang_en'],
                            ],
                        ],
                    ]),
                ]);
            } else {
                $this->showMainMenu();
            }

            return;
        }
    }
}
