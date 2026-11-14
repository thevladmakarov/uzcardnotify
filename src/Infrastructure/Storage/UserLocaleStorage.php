<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Enum\Locale;

final readonly class UserLocaleStorage
{
    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? sys_get_temp_dir() . '/user_locales.json';
    }

    public function get(int $chatId): Locale
    {
        $data = $this->readAll();
        $langCode = $data[(string)$chatId] ?? null;

        return $langCode !== null ? (Locale::tryFrom($langCode) ?? Locale::RU) : Locale::RU;
    }

    public function save(int $chatId, Locale $locale): void
    {
        $data = $this->readAll();
        $data[(string)$chatId] = $locale->value;

        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * @return array<string, string>
     */
    private function readAll(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }
}
