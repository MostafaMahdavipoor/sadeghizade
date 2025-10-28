<?php

namespace Bot;

use Config\AppConfig;
use DateTime;
use DateTimeZone;
use Exception;
use Payment\ZarinpalPaymentHandler;

date_default_timezone_set('Asia/Tehran');
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . "/../config/jdf.php";
require_once __DIR__ . "/PendingMessageManager.php";
require_once __DIR__ . "/Functions.php";
class HandleCallbackQuery
{

    use Functions;

    private $chatId;
    private $text;
    private $messageId;
    private $message;
    public  $db;
    private $fileHandler;
    private $zarinpalPaymentHandler;
    private $callbackId;
    private $AIChatHandler;

    private $botToken;
    private $botLink;
    private $sendRequest;
    private $aiHandler;

    public function __construct($chatId, $text, $messageId, $message, $callbackId)
    {
        $this->chatId      = $chatId;
        $this->text        = $text;
        $this->messageId   = $messageId;
        $this->message     = $message;
        $this->callbackId  = $callbackId;
        $this->db          = new Database();
        $this->fileHandler = new FileHandler();
        $config            = AppConfig::getConfig();
        $this->botToken    = $config['bot']['token'];
        $this->botLink     = $config['bot']['bot_link'];

        $this->zarinpalPaymentHandler = new ZarinpalPaymentHandler();
    }
}