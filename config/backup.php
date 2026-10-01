<?php

return [

    /*
    | Database backups (Admin → Sistem Sağlığı → Yedekler, `php artisan hrd:yedek`).
    | Files are written to storage/app/private/{path}; they are never publicly reachable.
    */

    'path' => 'backups',

    // Number of backups kept; older ones are deleted after each successful backup.
    'keep' => (int) env('BACKUP_KEEP', 14),

    // mysqldump executable. On Laragon e.g. C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),

    // Maximum age (hours) of the last backup before the health page warns.
    'max_age_hours' => 26,

];
