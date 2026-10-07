@extends('layouts.admin')
@php $isNew = ! $article->exists; @endphp
@section('title', $isNew ? 'Artikel baru' : $article->title)
@section('heading', $isNew ? 'Artikel baru' : 'Edit artikel')
@section('actions')
    <a class="btn btn--ghost" href="{{ route('admin.articles.index') }}">Kembali</a>
    @unless ($isNew)<a class="btn btn--ghost" href="{{ $article->url() }}" target="_blank" rel="noopener">Lihat di toko</a>@endunless
@endsection
@section('content')
@if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
<form class="card" method="post" enctype="multipart/form-data" action="{{ $isNew ? route('admin.articles.store') : route('admin.articles.update', $article) }}">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <div class="field"><label for="title">Judul</label><input id="title" type="text" name="title" value="{{ old('title', $article->title) }}" required maxlength="160"></div>
    <div class="form-grid">
        <div class="field"><label for="topic">Topik</label><input id="topic" type="text" name="topic" value="{{ old('topic', $article->topic) }}" maxlength="60" placeholder="Tips, Info toko, ..."></div>
        <div class="field"><label for="author">Penulis</label><input id="author" type="text" name="author" value="{{ old('author', $article->author) }}" maxlength="80"></div>
        <div class="field"><label for="published_at">Tanggal terbit</label><input id="published_at" type="datetime-local" name="published_at" value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}"><div class="hint">Isi tanggal depan untuk menjadwalkan.</div></div>
        <div class="field"><label for="cover">Gambar sampul (maks. 5 MB)</label><input id="cover" type="file" name="cover" accept="image/*"><div class="hint">Disarankan rasio 16:9, misalnya 1600 x 900.</div></div>
    </div>
    @if ($article->coverUrl('card'))
        <div class="row" style="margin-bottom:1rem"><img src="{{ $article->coverUrl('card') }}" alt="" style="width:12rem;border-radius:8px"><label class="check"><input type="checkbox" name="remove_cover" value="1"> Hapus sampul</label></div>
    @endif
    <div class="field"><label for="cover_alt">Keterangan sampul</label><input id="cover_alt" type="text" name="cover_alt" value="{{ old('cover_alt', $article->cover_alt) }}" maxlength="160"></div>
    <div class="field"><label for="excerpt">Ringkasan</label><input id="excerpt" type="text" name="excerpt" value="{{ old('excerpt', $article->excerpt) }}" maxlength="320"><div class="hint">Tampil di daftar artikel dan hasil pencarian Google.</div></div>
    <div class="field"><label for="body">Isi artikel</label><textarea id="body" name="body" rows="18" required>{{ old('body', $article->body) }}</textarea>
        <div class="hint">Format Markdown: <code>## Judul bagian</code>, <code>**tebal**</code>, <code>- daftar</code>, <code>[teks](https://...)</code>. Tag HTML dibuang demi keamanan.</div></div>
    <div class="form-grid">
        <div class="field"><label for="meta_title">Judul SEO</label><input id="meta_title" type="text" name="meta_title" value="{{ old('meta_title', $article->meta_title) }}" maxlength="70"></div>
        <div class="field"><label for="meta_description">Deskripsi SEO</label><input id="meta_description" type="text" name="meta_description" value="{{ old('meta_description', $article->meta_description) }}" maxlength="320"></div>
    </div>
    <div class="row" style="margin-bottom:1rem">
        <label class="check"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $article->is_published))> Terbitkan</label>
        @unless ($isNew)<label class="check"><input type="checkbox" name="regenerate_slug" value="1"> Perbarui alamat sesuai judul baru</label>@endunless
    </div>
    <div class="row">
        <button class="btn">{{ $isNew ? 'Simpan artikel' : 'Simpan perubahan' }}</button>
    </div>
</form>
@unless ($isNew)
<form method="post" action="{{ route('admin.articles.destroy', $article) }}" onsubmit="return confirm('Hapus artikel ini selamanya?')">@csrf @method('DELETE')<button class="btn btn--ghost">Hapus artikel</button></form>
@endunless
@endsection
