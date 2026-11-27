<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') · Kanal IT</title>
  <link rel="icon" href="/favicon.ico?v=setneg">
  <link rel="apple-touch-icon" href="/setneg.png">
  <link rel="stylesheet" href="/portal.css?v=nav-lobby-save-1">
  <script defer src="/portal.js?v=nav-lobby-save-1"></script>
</head>
<body>
<header class="site-header">
  <div class="wrap bar">
    <a class="brand" href="/"><img src="/setneg.png" width="40" height="40" alt="Logo Setneg"><span>Kanal IT</span></a>
    @auth
      <nav class="main-nav" aria-label="Navigasi utama">
        <a href="/" @if(request()->is('/')) aria-current="page" @endif>Beranda</a>
        @if(auth()->user()->hasMenuPermission('accounts') || auth()->user()->hasMenuPermission('reports') || auth()->user()->hasMenuPermission('zoom') || auth()->user()->hasMenuPermission('pins'))
          <details class="nav-group">
            <summary @if(request()->is('akun*','laporan*','zoom*','penting*')) class="is-current" @endif>Operasional</summary>
            <div class="nav-dropdown">
              @if(auth()->user()->hasMenuPermission('accounts'))<a href="/akun" @if(request()->is('akun*')) aria-current="page" @endif>Clipboard Akun</a>@endif
              @if(auth()->user()->hasMenuPermission('reports'))<a href="/laporan" @if(request()->is('laporan') || (request()->is('laporan/*') && !request()->is('laporan/bot'))) aria-current="page" @endif>Laporan harian</a>@endif
              @if(auth()->user()->hasMenuPermission('zoom'))<a href="/zoom" @if(request()->is('zoom*')) aria-current="page" @endif>Permintaan Zoom</a>@endif
              @if(auth()->user()->hasMenuPermission('pins'))<a href="/penting" @if(request()->is('penting*')) aria-current="page" @endif>Postingan penting</a>@endif
            </div>
          </details>
        @endif
        @if(auth()->user()->hasMenuPermission('lobby') || auth()->user()->hasMenuPermission('announcer'))
          <details class="nav-group">
            <summary @if(request()->is('lobby*','pengumuman*')) class="is-current" @endif>Tampilan & siaran</summary>
            <div class="nav-dropdown">
              @if(auth()->user()->hasMenuPermission('lobby'))<a href="/lobby" @if(request()->is('lobby*')) aria-current="page" @endif>Layar Lobby</a>@endif
              @if(auth()->user()->hasMenuPermission('announcer'))<a href="/pengumuman" @if(request()->is('pengumuman*')) aria-current="page" @endif>Pengumuman Kantor</a>@endif
            </div>
          </details>
        @endif
        @if(auth()->user()->hasMenuPermission('bot_guide'))<a href="/panduan-bot" @if(request()->is('panduan-bot')) aria-current="page" @endif>Panduan Bot</a>@endif
      </nav>
      <details class="menu">
        <summary>{{ auth()->user()->name }} ▾</summary>
        <div>
          @if(auth()->user()->hasMenuPermission('report_bot'))<a href="/laporan/bot">Bot & pegawai</a>@endif
          @if(auth()->user()->hasMenuPermission('access'))<a href="/akses">Akses pengguna</a>@endif
          <a href="/pengaturan">Ganti password</a>
          <form action="/keluar" method="post">@csrf<button>Keluar</button></form>
        </div>
      </details>
    @else
      <span class="small">Ruang kerja internal</span>
    @endauth
  </div>
</header>
<main class="wrap">
  @if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
  @if($errors->any())<p role="alert" class="notice error">{{ $errors->first() }}</p>@endif
  @yield('content')
</main>
<div id="toast" role="status"></div>
</body>
</html>
