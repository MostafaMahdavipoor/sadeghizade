<?php

namespace Bot;

use Config\AppConfig;
use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private ?PDO $pdo;
    private string $botLink;

    public function __construct()
    {
        $config = AppConfig::get();
        $this->botLink = $config['bot']['bot_link'];
        $dbConfig = $config['database'];

        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
        } catch (PDOException $e) {
            error_log("❌ Database Connection Failed: " . $e->getMessage());
            exit();
        }
    }

    public function query(string $sql, array $params = []): PDOStatement|false
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("❌ SQL Query Failed: " . $e->getMessage());
            return false;
        }
    }

    
    public function saveUser($user, $entryToken = null): void
    {
        $sql = "
            INSERT INTO users (chat_id, username, first_name, last_name, language, last_activity, entry_token) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE 
                username = VALUES(username), 
                first_name = VALUES(first_name), 
                last_name = VALUES(last_name), 
                language = VALUES(language), 
                last_activity = NOW()
        ";

        $params = [
            $user['id'],
            $user['username'] ?? '',
            $user['first_name'] ?? '',
            $user['last_name'] ?? '',
            $user['language_code'] ?? 'en',
            $entryToken
        ];

        $this->query($sql, $params);
    }

    //   -------------------------------- users
    public function getAllUsers(): array
    {
        $stmt = $this->query("SELECT * FROM users");
        return $stmt ? $stmt->fetchAll() : [];
    }
    public function getUsernameByChatId($chatId): string
    {
        $stmt = $this->query("SELECT username FROM users WHERE chat_id = ?", [$chatId]);
        $result = $stmt ? $stmt->fetchColumn() : null;
        return $result ?? 'Unknown';
    }
    public function setUserLanguage($chatId, $language): bool
    {
        $stmt = $this->query("UPDATE users SET language = ? WHERE chat_id = ?", [$language, $chatId]);
        return (bool)$stmt;
    }
    public function getUserByUsername($username): array|false
    {
        $stmt = $this->query("SELECT * FROM users WHERE username = ? LIMIT 1", [$username]);
        return $stmt ? $stmt->fetch() : false;
    }
    public function getUserLanguage($chatId): string
    {
        $stmt = $this->query("SELECT language FROM users WHERE chat_id = ? LIMIT 1", [$chatId]);
        $result = $stmt ? $stmt->fetchColumn() : null;
        return $result ?? 'fa';
    }
    public function getUserInfo($chatId): array|false
    {
        $stmt = $this->query("SELECT * FROM users WHERE chat_id = ?", [$chatId]);
        return $stmt ? $stmt->fetch() : false;
    }

    /**
     * تابع بازنویسی شده با PDO
     */
    public function getUserByChatId($chatId)
    {
        // کامای اضافی بعد از is_admin در کوئری شما حذف شد
        $query = "SELECT `id`, `chat_id`, `username`, `first_name`, `last_name`, 
                         `join_date`, `last_activity`, `status`, `language`, 
                         `is_admin` 
                  FROM `users` 
                  WHERE `chat_id` = ? 
                  LIMIT 1";

        // استفاده از متد query موجود در کلاس که PDO است
        $stmt = $this->query($query, [$chatId]);

        if (!$stmt) {
             error_log("❌ Query failed for getUserByChatId: " . $chatId);
             return null;
        }

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function getUserByChatIdOrUsername($identifier): array|false
    {
        if (is_numeric($identifier)) {
            $stmt = $this->query("SELECT * FROM users WHERE chat_id = ?", [$identifier]);
        } else {
            $username = ltrim($identifier, '@');
            $stmt = $this->query("SELECT * FROM users WHERE username = ?", [$username]);
        }
        return $stmt ? $stmt->fetch() : false;
    }
    public function getUserFullName($chatId): string
    {
        $stmt = $this->query("SELECT first_name, last_name FROM users WHERE chat_id = ?", [$chatId]);
        $user = $stmt ? $stmt->fetch() : null;
        if (!$user) {
            return '';
        }
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }
    public function getUsersBatch($limit = 20, $offset = 0): array
    {
        $sql = "SELECT id, chat_id, username, first_name, last_name, join_date, last_activity, status, language, is_admin, entry_token 
                FROM users 
                ORDER BY id ASC 
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$limit, $offset]);
        return $stmt ? $stmt->fetchAll() : [];
    }
    public function updateUserStatus($chatId, $status): bool
    {
        $stmt = $this->query("UPDATE users SET status = ? WHERE chat_id = ?", [$status, $chatId]);
        return $stmt && $stmt->rowCount() > 0;
    }
    public function getUserByUserId($userId): array|false
    {
        $stmt = $this->query("SELECT * FROM users WHERE chat_id = ? LIMIT 1", [$userId]);
        return $stmt ? $stmt->fetch() : false;
    }

    //   -------------------------------- admins
    public function isAdmin($chatId): bool
    {
        $stmt = $this->query("SELECT is_admin FROM users WHERE chat_id = ?", [$chatId]);
        $user = $stmt ? $stmt->fetch() : null;
        return $user && $user['is_admin'] == 1;
    }
    public function getAdmins(): array
    {
        $stmt = $this->query("SELECT id, chat_id, username FROM users WHERE is_admin = ?", [1]);
        return $stmt ? $stmt->fetchAll() : [];
    }
    //   -------------------------------- 
}