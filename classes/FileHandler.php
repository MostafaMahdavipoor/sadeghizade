<?php

namespace Bot;

class FileHandler
{
    private string $storageFile = __DIR__ . '/../parent_ids.json';
    private function ensureFileExists(): void
    {
        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    //   -------------------------------- State Management
    
    /**
     * ذخیره حالت (state) فعلی کاربر
     */
    public function saveState(int|string $chatId, mixed $state): void
    {
        $allData = $this->getAllData();
        $allData[$chatId]['state'] = $state;
        $this->saveAllData($allData);
    }

    /**
     * دریافت حالت (state) فعلی کاربر
     */
    public function getState(int|string $chatId): mixed
    {
        $allData = $this->getAllData();
        return $allData[$chatId]['state'] ?? null;
    }

    //   -------------------------------- Data Management (داده‌های موقت)

    /**
     * ذخیره داده‌های موقت (مثلاً اطلاعات فرم ثبت نام)
     */
    public function saveData(int|string $chatId, mixed $data): void
    {
        $allData = $this->getAllData();
        $allData[$chatId]['data'] = $data;
        $this->saveAllData($allData);
    }

    /**
     * دریافت داده‌های موقت کاربر
     */
    public function getData(int|string $chatId): array
    {
        $allData = $this->getAllData();
        return $allData[$chatId]['data'] ?? [];
    }
    
    //   -------------------------------- Message ID Management

    /**
     * افزودن یک آیدی پیام به لیست پیام‌های کاربر
     */
    public function addMessageId(int|string $chatId, int|string $messageId): void
    {
        $allData = $this->getAllData();
        $allData[$chatId]['message_ids'][] = $messageId;
        $this->saveAllData($allData);
    }

    /**
     * دریافت لیست آیدی پیام‌های کاربر
     */
    public function getMessageIds(int|string $chatId): array
    {
        $allData = $this->getAllData();
        return $allData[$chatId]['message_ids'] ?? [];
    }

    /**
     * پاک کردن لیست آیدی پیام‌های کاربر
     */
    public function clearMessageIds(int|string $chatId): void
    {
        $allData = $this->getAllData();
        unset($allData[$chatId]['message_ids']);
        $this->saveAllData($allData);
    }

    /**
     * ذخیره یک آیدی پیام خاص (مثلاً پیام اصلی منو)
     */
    public function saveMessageId(int|string $chatId, int|string $messageId): void
    {
        $allData = $this->getAllData();
        $allData[$chatId]['message_id'] = $messageId;
        $this->saveAllData($allData);
    }

    /**
     * دریافت آیدی پیام خاص کاربر
     */
    public function getMessageId(int|string $chatId): int|string|null
    {
        $allData = $this->getAllData();
        return $allData[$chatId]['message_id'] ?? null;
    }

    //   -------------------------------- Core I/O (Private)

    /**
     * خواندن تمام محتوای فایل ذخیره‌سازی
     */
    private function getAllData(): array
    {
        $this->ensureFileExists(); // اطمینان از وجود فایل قبل از خواندن
        
        $content = file_get_contents($this->storageFile);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in {$this->storageFile}: " . json_last_error_msg());
            return [];
        }

        return $data ?? [];
    }

    /**
     * نوشتن تمام محتوا در فایل ذخیره‌سازی (با قفل انحصاری)
     */
    private function saveAllData(array $data): void
    {
        $this->ensureFileExists(); // اطمینان از وجود فایل قبل از نوشتن

        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $fp = fopen($this->storageFile, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);      // پاک کردن محتوای فایل
            fwrite($fp, $jsonData); // نوشتن داده‌های جدید
            fflush($fp);            // اطمینان از نوشته شدن داده‌ها
            flock($fp, LOCK_UN);    // آزاد کردن قفل
            fclose($fp);
        }
    }
}