{{-- Self-contained on purpose: no Vite, no database — it must render even when those are down. --}}
<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('code') · @yield('title')</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; background: #F3F6FB; color: #15233B;
                font-family: 'Manrope', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
            header { display: flex; align-items: center; gap: 11px; padding: 20px 24px; }
            .logo { width: 34px; height: 34px; border-radius: 9px; background: #11A795; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; }
            .brand { font-size: 15px; font-weight: 800; }
            main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
            .box { width: 100%; max-width: 440px; text-align: center; }
            .code { font-size: 88px; line-height: 1; font-weight: 800; letter-spacing: -0.04em; color: @yield('color', '#163B66'); }
            h1 { margin: 18px 0 8px; font-size: 22px; font-weight: 800; letter-spacing: -0.01em; }
            p { margin: 0 auto; max-width: 340px; font-size: 14px; line-height: 1.55; color: #69788C; }
            .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 24px; }
            .btn { display: inline-flex; align-items: center; height: 42px; padding: 0 18px; border-radius: 10px; font-size: 13.5px; font-weight: 700; text-decoration: none; cursor: pointer; font-family: inherit; }
            .primary { background: #163B66; color: #fff; border: 0; box-shadow: 0 5px 14px rgba(22, 59, 102, .22); }
            .primary:hover { background: #1D4C82; }
            .secondary { background: #fff; color: #2B3A50; border: 1.5px solid #E0E6EE; }
            .secondary:hover { border-color: #163B66; }
            .note { margin-top: 22px; padding: 12px 16px; border-radius: 12px; background: #fff; border: 1px solid #EAEEF4; font-size: 13px; color: #3B4A60; text-align: start; }
            footer { padding: 18px; text-align: center; font-size: 12px; color: #8493A8; }
        </style>
    </head>
    <body>
        <header>
            <span class="logo">O</span>
            <span class="brand">{{ config('app.name', 'Laravel') }}</span>
        </header>
        <main>
            <div class="box">
                <div class="code">@yield('code')</div>
                <h1>@yield('title')</h1>
                <p>@yield('message')</p>
                @hasSection('note')
                    <div class="note">@yield('note')</div>
                @endif
                <div class="actions">
                    @section('actions')
                        <a href="{{ url('/') }}" class="btn primary">Ana sayfaya dön</a>
                        <a href="javascript:history.back()" class="btn secondary">Geri git</a>
                    @show
                </div>
            </div>
        </main>
        <footer>Sorun devam ederse sorumlu bordro uzmanınızla iletişime geçin.</footer>
    </body>
</html>
