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
            // Logger::log('error', 'DB Connection Failed', $e->getMessage()); // اگر لاگر دارید
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
            error_log("❌ SQL Query Failed: " . $e->getMessage() . " | SQL: " . $sql);
            // Logger::log('error', 'SQL Query Failed', $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            return false;
        }
    }


    public function saveUser($user, $entryToken = null): void
    {
        // این متد کاربر را در جدول 'users' ذخیره می‌کند
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

    public function getUserInfo($chatId): array|false
    {
        $stmt = $this->query("SELECT * FROM users WHERE chat_id = ?", [$chatId]);
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

    // ======================================================
    // متدهای جدید برای ربات مشاوره
    // ======================================================

    //   -------------------------------- students
    /**
     * یک دانش آموز جدید ایجاد می‌کند یا وضعیت فعلی را برای ثبت نام آماده می‌کند
     */
    public function createStudent(int $chatId): void
    {
        $sql = "
            INSERT INTO students (chat_id, status, created_at) 
            VALUES (?, 'pending_registration', NOW())
            ON DUPLICATE KEY UPDATE 
                status = 'pending_registration' 
        ";
        $this->query($sql, [$chatId]);
    }

    /**
     * اطلاعات دانش آموز را در پایان ثبت نام به‌روزرسانی می‌کند
     */
    public function finalizeStudentRegistration(int $chatId, string $firstName, string $lastName, string $major, string $grade, string $reportTime): bool
    {
        $sql = "
            UPDATE students 
            SET 
                first_name = ?, 
                last_name = ?, 
                major = ?, 
                grade = ?, 
                report_time = ?, 
                status = 'active'
            WHERE chat_id = ?
        ";
        $stmt = $this->query($sql, [$firstName, $lastName, $major, $grade, $reportTime, $chatId]);
        return $stmt && $stmt->rowCount() > 0;
    }

    public function getStudent(int $chatId): array|false
    {
        $stmt = $this->query("SELECT * FROM students WHERE chat_id = ?", [$chatId]);
        return $stmt ? $stmt->fetch() : false;
    }

    public function getStudentMajor(int $chatId): string|false
    {
        $stmt = $this->query("SELECT major FROM students WHERE chat_id = ?", [$chatId]);
        return $stmt ? $stmt->fetchColumn() : false;
    }
    public function getLessons(?int $parentId, string $major): array
    {
        // <=> یک عملگر NULL-safe equals است (برای زمانی که parentId=NULL است)
        $sql = "
            SELECT lesson_id, name 
            FROM lessons 
            WHERE parent_id <=> ?
              AND (major = ? OR major = 'all')
            ORDER BY sort_order, name
        ";

        $stmt = $this->query($sql, [$parentId, $major]);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * اطلاعات یک درس خاص را با ID آن برمی‌گرداند
     */
    public function getLessonById(int $lessonId): array|false
    {
        $sql = "SELECT * FROM lessons WHERE lesson_id = ? LIMIT 1";
        $stmt = $this->query($sql, [$lessonId]);
        return $stmt ? $stmt->fetch() : false;
    }
    /**
     * دانش آموزانی را برمی‌گرداند که زمان گزارش آن‌ها فرا رسیده است
     */
    public function getUsersToNotify(string $currentTime, string $currentDate): array
    {
        // این کوئری دانش آموزان فعالی را انتخاب می‌کند
        // که زمان گزارششان <= زمان فعلی است
        // و (LEFT JOIN) هیچ گزارشی (reports r) برای تاریخ امروز ندارند.
        $sql = "
            SELECT s.chat_id
            FROM students s
            LEFT JOIN reports r ON s.chat_id = r.chat_id AND r.report_date = ?
            WHERE s.status = 'active'
              AND s.report_time <= ?
              AND r.report_id IS NULL
        ";

        $stmt = $this->query($sql, [$currentDate, $currentTime]);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * دانش آموزانی را برمی‌گرداند که گزارش نداده‌اند و نیاز به یادآوری دارند
     */
    public function getUsersToRemind(): array
    {
        $sql = "
            SELECT r.report_id, r.chat_id 
            FROM reports r
            WHERE r.status = 'pending' 
              AND r.reminder_sent = 0 
              AND r.notified_at <= NOW() - INTERVAL 1 HOUR
        ";
        $stmt = $this->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }


    //   -------------------------------- reports
    /**
     * گزارش روزانه را برای دانش آموز ایجاد می‌کند (توسط Cron Job)
     */
    public function createDailyReport(int $chatId, string $date, string $notifiedAt): int|false
    {
        $sql = "
            INSERT INTO reports (chat_id, report_date, status, notified_at) 
            VALUES (?, ?, 'pending', ?)
        ";
        $stmt = $this->query($sql, [$chatId, $date, $notifiedAt]);
        return $stmt ? $this->pdo->lastInsertId() : false;
    }
    public function getStudentDetailedReportData(int $studentChatId): array
    {
        $sql = "
            SELECT
                r.report_date,
                re.lesson_name,
                re.topic,
                re.study_time,
                re.test_count
            FROM report_entries re
            JOIN reports r ON re.report_id = r.report_id
            WHERE r.chat_id = ? AND r.status = 'submitted'
            ORDER BY r.report_date DESC, re.entry_id ASC
        ";

        $stmt = $this->query($sql, [$studentChatId]);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function getTodaysReport(int $chatId): array|false
    {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM reports WHERE chat_id = ? AND report_date = ? LIMIT 1";
        $stmt = $this->query($sql, [$chatId, $today]);
        return $stmt ? $stmt->fetch() : false;
    }

    public function getReportById(int $reportId): array|false
    {
        $sql = "SELECT * FROM reports WHERE report_id = ? LIMIT 1";
        $stmt = $this->query($sql, [$reportId]);
        return $stmt ? $stmt->fetch() : false;
    }


    public function updateReportStatus(int $reportId, string $status): bool
    {
        $sql = "UPDATE reports SET status = ? WHERE report_id = ?";
        $stmt = $this->query($sql, [$status, $reportId]);
        return $stmt && $stmt->rowCount() > 0;
    }

    public function updateReportReason(int $reportId, ?string $reason, ?string $photoId): bool
    {
        $sql = "UPDATE reports SET reason = ?, reason_photo_id = ?, status = 'reason_provided' WHERE report_id = ?";
        $stmt = $this->query($sql, [$reason, $photoId, $reportId]);
        return $stmt && $stmt->rowCount() > 0;
    }

    public function updateReportReminderSent(int $reportId): bool
    {
        $sql = "UPDATE reports SET reminder_sent = 1 WHERE report_id = ?";
        $stmt = $this->query($sql, [$reportId]);
        return $stmt && $stmt->rowCount() > 0;
    }


    public function addReportEntry(int $reportId, string $lessonName, string $topic, int $studyTime, int $testCount): bool
    {
        $sql = "
            INSERT INTO report_entries (report_id, lesson_name, topic, study_time, test_count) 
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $this->query($sql, [$reportId, $lessonName, $topic, $studyTime, $testCount]);
        return (bool)$stmt;
    }


    public function getReportEntries(int $reportId): array
    {
        $sql = "SELECT * FROM report_entries WHERE report_id = ?";
        $stmt = $this->query($sql, [$reportId]);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function getActiveStudents(): array
    {
        $sql = "
            SELECT chat_id, first_name, last_name, grade 
            FROM students 
            WHERE status = 'active' 
            ORDER BY last_name, first_name
        ";

        $stmt = $this->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }


    public function getStudentStats(int $chat_id): array|false
    {
        $sql = "
            SELECT 
                s.first_name, s.last_name, s.major, s.grade,
                
                (SELECT COUNT(*) FROM reports r 
                 WHERE r.chat_id = s.chat_id AND r.status = 'submitted') 
                 as submitted_reports,
                 
                (SELECT COUNT(*) FROM reports r 
                 WHERE r.chat_id = s.chat_id AND r.status != 'submitted' AND r.report_date < CURDATE()) 
                 as missed_reports,
                 
                IFNULL((SELECT SUM(re.study_time) FROM report_entries re 
                        JOIN reports r ON re.report_id = r.report_id 
                        WHERE r.chat_id = s.chat_id), 0) 
                as total_study_time,
                
                IFNULL((SELECT SUM(re.test_count) FROM report_entries re 
                        JOIN reports r ON re.report_id = r.report_id 
                        WHERE r.chat_id = s.chat_id), 0) 
                as total_test_count
                
            FROM students s
            WHERE s.chat_id = ?
        ";

        $stmt = $this->query($sql, [$chat_id]);
        return $stmt ? $stmt->fetch() : false;
    }
}
