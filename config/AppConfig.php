<?php

namespace Config;

use Dotenv\Dotenv;
use Exception;

class AppConfig
{
    
    private static array $config = [];

   
    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if (empty(self::$config)) {
            self::load();
        }
        if ($key === null) {
            return self::$config;
        }
        $config = self::$config;
        foreach (explode('.', $key) as $segment) {
            if (is_array($config) && array_key_exists($segment, $config)) {
                $config = $config[$segment];
            } else {
                return $default;
            }
        }

        return $config;
    }

    private static function load(): void
    {
        if (!empty(self::$config)) {
            return;
        }

        try {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->safeLoad();

            // متغیرهایی که حتما باید وجود داشته باشند و خالی هم نباشند
            $dotenv->required([
                'DB_HOST',
                'DB_USERNAME',
                'DB_DATABASE',
                'BOT_TOKEN',
                'BOT_LINK'
            ])->notEmpty();

            // DB_PASSWORD فقط باید وجود داشته باشد، اما می‌تواند خالی باشد
            $dotenv->required('DB_PASSWORD');

            self::$config = [
                'database' => [
                    'host' => $_ENV['DB_HOST'],
                    'username' => $_ENV['DB_USERNAME'],
                    'password' => $_ENV['DB_PASSWORD'],
                    'database' => $_ENV['DB_DATABASE'],
                ],
                'bot' => [
                    'token' => $_ENV['BOT_TOKEN'],
                    'merchant_id' => $_ENV['MERCHANT_ID'] ?? '', 
                    'bot_link' => $_ENV['BOT_LINK'],
                ],
                'ai' => [
                    'gpt' => [
                        'api_key' => $_ENV['GPT_API_KEY'] ?? '',
                        'model' => $_ENV['GPT_MODEL'] ?? 'gpt-4',
                        'temperature' => (float) ($_ENV['GPT_TEMPERATURE'] ?? 0.7),
                    ],
                    'deepseek' => [
                        'api_key' => $_ENV['DEEPSEEK_API_KEY'] ?? '',
                        'model' => $_ENV['DEEPSEEK_MODEL'] ?? 'deepseek-chat',
                        'temperature' => (float) ($_ENV['DEEPSEEK_TEMPERATURE'] ?? 0.7),
                    ],
                    'qwen' => [
                        'api_key' => $_ENV['QWEN_API_KEY'] ?? '',
                        'model' => $_ENV['QWEN_MODEL'] ?? 'qwen-chat',
                        'temperature' => (float) ($_ENV['QWEN_TEMPERATURE'] ?? 0.7),
                    ]
                ]
            ];
        } catch (Exception $e) {
            error_log("❌ Configuration Error: " . $e->getMessage());
            die("Application configuration is invalid. Please check the logs.");
        }
    }
}