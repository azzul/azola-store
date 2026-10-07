@extends('layouts.admin')
@section('title', 'Pengaturan toko')
@section('heading', 'Pengaturan toko')
@section('content')
@if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
<p class="muted">Nama, kontak, alamat, sosmed, warna, dan logo. Tersimpan di database dan langsung tampil di toko. Kosongkan satu isian untuk kembali ke nilai bawaan dari berkas .env.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}">
    @csrf @method('PUT')

    <section class="card">
        <h2>Logo</h2>
        <div class="row" style="align-items:center">
            @if ($logo)<img src="{{ asset($logo) }}" alt="Logo saat ini" style="height:56px;width:auto;border-radius:10px;background:#f1f4ee;padding:4px">@else<span class="muted">Memakai logo bawaan.</span>@endif
            <div class="field" style="margin:0"><label for="logo">Unggah logo (PNG, JPG, WebP, atau SVG, maks. 1 MB)</label><input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"><div class="hint">Disarankan persegi, misalnya 256 x 256.</div></div>
            @if ($logo)<label class="check"><input type="checkbox" name="remove_logo" value="1"> Kembali ke logo bawaan</label>@endif
        </div>
    </section>

    @foreach ($groups as $group => $fields)
        <section class="card">
            <h2>{{ $group }}</h2>
            <div class="form-grid">
                @foreach ($fields as $f)
                    <div class="field" @if (in_array($f['type'], ['textarea', 'hours'])) style="grid-column:1/-1" @endif>
                        <label for="s_{{ $f['key'] }}">{{ $f['label'] }}</label>
                        @if (in_array($f['type'], ['textarea', 'hours']))
                            <textarea id="s_{{ $f['key'] }}" name="{{ $f['key'] }}" rows="{{ $f['type'] === 'hours' ? 3 : 2 }}">{{ old($f['key'], $f['value']) }}</textarea>
                        @elseif ($f['type'] === 'color')
                            <input id="s_{{ $f['key'] }}" type="text" name="{{ $f['key'] }}" value="{{ old($f['key'], $f['value']) }}" placeholder="#0F5C46" maxlength="7">
                        @else
                            <input id="s_{{ $f['key'] }}" type="{{ $f['type'] === 'email' ? 'email' : ($f['type'] === 'number' ? 'number' : 'text') }}" name="{{ $f['key'] }}" value="{{ old($f['key'], $f['value']) }}" @if ($f['type'] === 'number') min="0" @endif>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <button class="btn">Simpan pengaturan</button>
</form>
@endsection
