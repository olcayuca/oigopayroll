<?php

namespace App\System;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Recent errors from storage/logs (single or daily channel). Only the first line of each
 * entry is shown; stack traces stay in the file.
 */
class ErrorLog
{
    /** Bytes read from the end of each file. */
    private const TAIL_BYTES = 1024 * 1024;

    /**
     * @return list<array{at: Carbon|null, level: string, message: string}>
     */
    public function recent(int $limit = 50): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn (string $a, string $b) => (int) filemtime($b) <=> (int) filemtime($a));

        $entries = [];

        foreach (array_slice($files, 0, 3) as $file) {
            foreach ($this->parse($this->tail($file)) as $entry) {
                $entries[] = $entry;
            }
        }

        usort($entries, fn (array $a, array $b) => ($b['at']?->getTimestamp() ?? 0) <=> ($a['at']?->getTimestamp() ?? 0));

        return array_slice($entries, 0, $limit);
    }

    private function tail(string $file): string
    {
        $size = (int) filesize($file);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return '';
        }

        fseek($handle, max(0, $size - self::TAIL_BYTES));
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * @return list<array{at: Carbon|null, level: string, message: string}>
     */
    private function parse(string $content): array
    {
        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T][0-9:.+\-]+)\] [\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $content, $matches, PREG_SET_ORDER);

        return array_map(function (array $match) {
            try {
                $at = Carbon::parse($match[1]);
            } catch (Throwable) {
                $at = null;
            }

            return ['at' => $at, 'level' => $match[2], 'message' => mb_substr(trim($match[3]), 0, 400)];
        }, $matches);
    }
}
