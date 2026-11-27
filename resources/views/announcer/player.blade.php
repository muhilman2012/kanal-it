<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Speaker Pengumuman · Kanal IT</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="/announcer-player.css">
  <script defer src="/announcer-player.js"></script>
</head>
<body data-code="{{ $code }}">
  <main>
    <img src="/setneg.png" width="76" height="76" alt="">
    <p class="eyebrow">KANAL IT · SPEAKER KHUSUS</p>
    <h1>Pengumuman Kantor</h1>
    <p id="clock" class="clock"></p>
    <div class="status-card">
      <span id="status-dot" class="dot"></span>
      <div><strong id="status">Belum aktif</strong><p id="detail">Sambungkan komputer ini ke speaker kantor, lalu aktifkan pemutaran.</p></div>
    </div>
    <div class="voice-controls">
      <label for="voice-select">Suara pembaca</label>
      <select id="voice-select"><option value="">Suara bawaan browser</option></select>
      <p id="voice-help">Pilihan suara mengikuti browser dan perangkat speaker. Dengarkan uji suara untuk memilih karakter pria atau wanita yang tersedia.</p>
      <label for="voice-rate">Kecepatan suara <output id="rate-value">0,9×</output></label>
      <input id="voice-rate" type="range" min="0.7" max="1.1" step="0.05" value="0.9">
      <label for="voice-volume">Volume suara <output id="volume-value">80%</output></label>
      <input id="voice-volume" type="range" min="0.3" max="1" step="0.05" value="0.8">
    </div>
    <div class="buttons"><button id="activate">Aktifkan speaker & uji bel</button><button id="test-voice" hidden>Uji suara terpilih</button></div>
    <p class="note">Biarkan halaman ini terbuka pada perangkat speaker. Jadwal menggunakan WIB. Browser perlu satu klik untuk mengizinkan audio dan pembacaan teks.</p>
  </main>
</body>
</html>
