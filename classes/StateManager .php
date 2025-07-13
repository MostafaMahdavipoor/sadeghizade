<?php

namespace Bot;

class StateManager
{
    private $stateFilePath;
    private $lockFilePointer;

    public function __construct()
    {
        // اطمینان از وجود پوشه وضعیت
        $this->stateFilePath = __DIR__ . '/../data/state/'; 
        if (!is_dir($this->stateFilePath)) {
            mkdir($this->stateFilePath, 0777, true);
        }
    }
    
    /**
     * قفل‌گذاری روی فایل برای جلوگیری از تداخل
     * @param string $chatId
     * @return bool
     */
    public function lockState(string $chatId): bool
    {
        $lockFile = $this->stateFilePath . $chatId . '.lock';
        $this->lockFilePointer = fopen($lockFile, 'w');
        if ($this->lockFilePointer === false) {
            // اگر به هر دلیلی فایل قفل باز نشد، لاگ ثبت کن
            Logger::log('error', 'Locking Failed', "Could not open lock file for chat ID {$chatId}.");
            return false;
        }
        // تلاش برای گرفتن قفل انحصاری بدون منتظر ماندن
        return flock($this->lockFilePointer, LOCK_EX | LOCK_NB);
    }
    
    /**
     * آزادسازی قفل
     * @param string $chatId
     */
    public function unlockState(string $chatId): void
    {
        if ($this->lockFilePointer) {
            flock($this->lockFilePointer, LOCK_UN); // آزادسازی قفل
            fclose($this->lockFilePointer);         // بستن فایل
            $this->lockFilePointer = null;
            // فایل قفل را پس از آزاد کردن حذف می‌کنیم تا پوشه شلوغ نشود
            @unlink($this->stateFilePath . $chatId . '.lock');
        }
    }

    // --- مدیریت وضعیت اصلی فرم ---
    public function getState(string $chatId): ?array
    {
        $file = $this->stateFilePath . $chatId . '.json';
        if (!file_exists($file)) return null;
        $stateData = file_get_contents($file);
        return $stateData ? json_decode($stateData, true) : null;
    }

    public function saveState(string $chatId, array $state): void
    {
        $file = $this->stateFilePath . $chatId . '.json';
        file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT));
    }

    public function clearState(string $chatId): void
    {
        $stateFile = $this->stateFilePath . $chatId . '.json';
        if (file_exists($stateFile)) @unlink($stateFile);
        $this->clearVerificationCode($chatId); // کد تایید را هم پاک کن
    }

    // --- مدیریت بهینه و مجزا برای کد تایید با قفل اختصاصی ---
    private function getVerificationCodePath(string $chatId): string
    {
        return $this->stateFilePath . $chatId . '.vcode'; // پسوند را ساده‌تر می‌کنیم
    }

    public function saveVerificationCode(string $chatId, string $code): void
    {
        $path = $this->getVerificationCodePath($chatId);
        $data = json_encode(['code' => $code, 'expires_at' => time() + 300]); // 5 دقیقه اعتبار
        
        // قفل کردن فایل کد تایید قبل از نوشتن برای تضمین ذخیره کامل
        $fp = fopen($path, 'w');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $data);
            fflush($fp); // اطمینان از نوشته شدن داده‌ها روی دیسک
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public function getVerificationCode(string $chatId): ?string
    {
        $path = $this->getVerificationCodePath($chatId);
        if (!file_exists($path)) {
            return null;
        }
        
        // قفل کردن فایل کد تایید قبل از خواندن
        $fp = fopen($path, 'r');
        if (!$fp) return null;

        $content = '';
        if (flock($fp, LOCK_SH)) { // قفل اشتراکی برای خواندن
            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if (empty($content)) return null;
        
        $data = json_decode($content, true);
        
        if (empty($data['code']) || empty($data['expires_at']) || time() > $data['expires_at']) {
            @unlink($path);
            return null;
        }
        
        return $data['code'];
    }

    public function clearVerificationCode(string $chatId): void
    {
        $path = $this->getVerificationCodePath($chatId);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    // --- توابع مدیریت Pagination ---
    public function saveStepPage(string $chatId, string $stepKey, int $page): void
    {
        $state = $this->getState($chatId) ?? [];
        $state['pages'][$stepKey] = $page;
        $this->saveState($chatId, $state);
    }

    public function getStepPage(string $chatId, string $stepKey): int
    {
        $state = $this->getState($chatId);
        return $state['pages'][$stepKey] ?? 1;
    }
}