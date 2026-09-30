<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal Domains
    |--------------------------------------------------------------------------
    |
    | landing: public website (siteadi.com)
    | panel:   client firm users (panel.siteadi.com)
    | admin:   HRD super admins and payroll specialists (admin.siteadi.com)
    |
    | Sessions are host-only (SESSION_DOMAIN=null), so each portal has its own login.
    |
    */

    'landing' => env('LANDING_DOMAIN', 'oigopayroll.test'),

    'panel' => env('PANEL_DOMAIN', 'panel.oigopayroll.test'),

    'admin' => env('ADMIN_DOMAIN', 'admin.oigopayroll.test'),

];
