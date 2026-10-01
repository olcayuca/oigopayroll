<?php

namespace App\Console\Commands;

use App\System\Backups;
use App\System\HealthChecks;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('hrd:yedek')]
#[Description('Veritabanı yedeği al (storage/app/private/backups)')]
class BackupDatabase extends Command
{
    public function handle(Backups $backups): int
    {
        try {
            $result = $backups->create();
        } catch (Throwable $e) {
            $this->error('Yedek alınamadı: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Yedek alındı: {$result['file']} (".HealthChecks::bytes($result['size']).')');

        return self::SUCCESS;
    }
}
