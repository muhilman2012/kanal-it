<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>{{ $screen->name }} · Layar Lobby</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="/lobby-player.css">
    <script defer src="/lobby-player.js"></script>
</head>
<body data-playlist-url="/layar-lobby/{{ $token }}/playlist">
    <main id="stage" aria-live="off">
        <div class="waiting"><img src="/setneg.png" alt="" width="96" height="96"><h1>{{ $screen->name }}</h1><p>Menyiapkan tayangan…</p></div>
    </main>
    <div id="controls">
        <button type="button" id="sound" hidden>Aktifkan suara</button>
        <button type="button" id="fullscreen">Layar penuh</button>
    </div>
</body>
</html>
