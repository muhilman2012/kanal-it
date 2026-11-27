<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PinnedPostController extends Controller
{
    public function index()
    {
        $posts = DB::table('pinned_posts')->select('id','title','url','filename')->orderByDesc('id')->paginate(20);
        return view('pins', compact('posts'));
    }

    public function save(Request $request, ?int $id = null)
    {
        $current = $id ? DB::table('pinned_posts')->select('filename')->where('id',$id)->firstOrFail() : null;
        $data = $request->validate([
            'title'=>'required|string|max:120', 'url'=>['nullable','url:http,https','max:2048'],
            'file'=>['nullable','file','max:2048'],
        ]);
        if (!$request->filled('url') && !$request->hasFile('file') && !$current?->filename) {
            return back()->withErrors(['url'=>'Isi tautan atau pilih lampiran.'])->withInput();
        }
        $values=['title'=>$data['title'],'url'=>$data['url']??null,'updated_at'=>now()];
        if ($file=$request->file('file')) {
            $values['attachment']=base64_encode($file->getContent());
            $values['filename']=mb_substr(preg_replace('/[[:cntrl:]\/\\\\]+/u','-', $file->getClientOriginalName()),0,180);
            $values['mime']=$file->getMimeType() ?: 'application/octet-stream';
        }
        if ($id) DB::table('pinned_posts')->where('id',$id)->update($values);
        else DB::table('pinned_posts')->insert($values+['created_at'=>now()]);
        return redirect('/penting')->with('status','Postingan penting tersimpan.');
    }

    public function download(int $id)
    {
        $post=DB::table('pinned_posts')->whereNotNull('attachment')->where('id',$id)->firstOrFail();
        $fallback=preg_replace('/[^A-Za-z0-9._ -]/','_',Str::ascii($post->filename)) ?: 'lampiran';
        return response(base64_decode($post->attachment),200,['Content-Type'=>'application/octet-stream',
            'Content-Disposition'=>HeaderUtils::makeDisposition('attachment',$post->filename,$fallback),
            'X-Content-Type-Options'=>'nosniff']);
    }

    public function delete(int $id)
    {
        abort_unless(DB::table('pinned_posts')->where('id',$id)->delete(),404);
        return redirect('/penting')->with('status','Postingan penting dihapus.');
    }

    public static function forChat(string $text)
    {
        if (preg_match('~^/penting\s+(\d+)$~i',$text,$m)) {
            $post=DB::table('pinned_posts')->find($m[1]);
            if (!$post) return response()->json(['reply'=>'Postingan tidak ditemukan. Kirim /penting untuk melihat daftar.']);
            $answer=['reply'=>trim($post->title."\n".($post->url??''))];
            if ($post->filename) $answer['media']=['name'=>$post->filename,'mime'=>$post->mime,'data'=>$post->attachment];
            return response()->json($answer);
        }
        if (!preg_match('~^/penting(?:\s+halaman\s+([1-9]\d{0,5}))?$~i',$text,$m)) {
            return response()->json(['reply'=>'Kirim /penting atau /penting diikuti nomor postingan.']);
        }
        $posts=DB::table('pinned_posts')->orderBy('id')->simplePaginate(10,['id','title'],'page',(int)($m[1]??1));
        if ($posts->isEmpty()) return response()->json(['reply'=>'Belum ada postingan pada halaman ini.']);
        $lines=['*Postingan penting*', ''];
        foreach ($posts as $post) $lines[]='*'.$post->id.'. '.$post->title.'*';
        $lines[]='';
        $lines[]='Kirim /penting '.$posts->last()->id.' untuk melihat postingan tersebut.';
        if ($posts->hasMorePages()) $lines[]='Berikutnya: /penting halaman '.($posts->currentPage()+1);
        return response()->json(['reply'=>implode("\n",$lines)]);
    }
}
