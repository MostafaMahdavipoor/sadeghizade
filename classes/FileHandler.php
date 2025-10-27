<?php

namespace Bot;

class FileHandler
{

    private array $files = [
        1 => __DIR__ . '/../parent_ids.json',
        2 => __DIR__ . '/../states.json',
        3 => __DIR__ . '/../messages.json',
    ];

    private int $defaultFileKey = 1;

   
    private function getFile($fileKey = null): string
    {
        $fileKey = $fileKey ?? $this->defaultFileKey;

        if (!isset($this->files[$fileKey])) {
            throw new \InvalidArgumentException("❌ فایل با شماره {$fileKey} تعریف نشده.");
        }

        $file = $this->files[$fileKey];

        if (!file_exists($file)) {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $file;
    }

    public function saveState(int|string $chatId, mixed $state, $fileKey = null): void
    {
        $data = $this->getAllData($fileKey);
        $data[$chatId]['state'] = $state;
        $this->saveAllData($data, $fileKey);
    }

    public function getState(int|string $chatId, $fileKey = null): mixed
    {
        $data = $this->getAllData($fileKey);
        return $data[$chatId]['state'] ?? null;
    }

    public function addMessageId(int|string $chatId, int|string $messageId, $fileKey = null): void
    {
        $data = $this->getAllData($fileKey);
        $data[$chatId]['message_ids'][] = $messageId;
        $this->saveAllData($data, $fileKey);
    }

    public function getMessageIds(int|string $chatId, $fileKey = null): array
    {
        $data = $this->getAllData($fileKey);
        return $data[$chatId]['message_ids'] ?? [];
    }

    public function clearMessageIds(int|string $chatId, $fileKey = null): void
    {
        $data = $this->getAllData($fileKey);
        unset($data[$chatId]['message_ids']);
        $this->saveAllData($data, $fileKey);
    }

    public function saveMessageId(int|string $chatId, int|string $messageId, $fileKey = null): void
    {
        $data = $this->getAllData($fileKey);
        $data[$chatId]['message_id'] = $messageId;
        $this->saveAllData($data, $fileKey);
    }

    public function getMessageId(int|string $chatId, $fileKey = null): int|string|null
    {
        $data = $this->getAllData($fileKey);
        return $data[$chatId]['message_id'] ?? null;
    }

    private function getAllData($fileKey = null): array
    {
        $file = $this->getFile($fileKey);
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in {$file}: " . json_last_error_msg());
            return [];
        }

        return $data ?? [];
    }

    private function saveAllData(array $data, $fileKey = null): void
    {
        $file = $this->getFile($fileKey);
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $fp = fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $jsonData);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
