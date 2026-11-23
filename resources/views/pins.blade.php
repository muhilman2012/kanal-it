@extends('layout')
@section('title','Postingan penting')
@section('content')
<section class="heading"><div><a class="crumb" href="/">Beranda</a><h1>Postingan penting</h1><p>Tautan, file, dan gambar untuk tim IT. Ketik <code>/penting</code> di WhatsApp untuk melihat daftar.</p></div><button class="primary" data-open="pin-new">Tambah postingan</button></section>
<dialog id="pin-new"><h2>Tambah postingan</h2>@include('pin-form',['p'=>null])</dialog>
<div class="list">@forelse($posts as $p)<article class="entry"><div><strong>{{ $p->title }}</strong><div class="small">/penting {{ $p->id }}</div>
@if($p->url)<a href="{{ $p->url }}" target="_blank" rel="noopener noreferrer">Buka tautan</a>@endif
@if($p->filename)<div><a href="/penting/{{ $p->id }}/file">Unduh {{ $p->filename }}</a></div>@endif</div><button data-open="pin-{{ $p->id }}">Kelola</button></article>
<dialog id="pin-{{ $p->id }}"><h2>Kelola postingan</h2>@include('pin-form',['p'=>$p])
<form method="post" action="/penting/{{ $p->id }}" onsubmit="return confirm('Hapus postingan beserta lampirannya?')">@csrf @method('delete')<button class="danger">Hapus postingan</button></form></dialog>
@empty<div class="empty">Belum ada postingan penting.</div>@endforelse</div>@include('pager',['items'=>$posts])
@endsection