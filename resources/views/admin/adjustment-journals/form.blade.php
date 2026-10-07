@extends('layouts.admin')
@section('title', 'Jurnal manual')
@section('heading', 'Jurnal manual')
@section('content')
<div class="help">Untuk koreksi atau akrual yang tidak berasal dari transaksi biasa. Total debit harus sama dengan total kredit; satu baris diisi debit <em>atau</em> kredit.</div>
@php($rows = old('lines', [[], [], [], []]))
<form method="post" action="{{ route('admin.adjustment-journals.store') }}">@csrf
<section class="card"><div class="form-grid form-grid--tight">
    <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
    <div class="field"><label for="description">Keterangan</label><input id="description" name="description" value="{{ old('description') }}" required maxlength="200" placeholder="mis. Akrual listrik Oktober"></div>
</div></section>
<section class="card scroll"><table><thead><tr><th>Akun</th><th>Debit</th><th>Kredit</th><th>Memo</th></tr></thead><tbody id="jl">
@foreach ($rows as $i => $r)
<tr><td><select name="lines[{{ $i }}][account_id]" aria-label="Akun baris {{ $i + 1 }}"><option value="">-</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((int) ($r['account_id'] ?? 0) === $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></td>
<td><input class="js-d" type="number" min="0" name="lines[{{ $i }}][debit]" value="{{ $r['debit'] ?? '' }}" aria-label="Debit baris {{ $i + 1 }}"></td><td><input class="js-c" type="number" min="0" name="lines[{{ $i }}][credit]" value="{{ $r['credit'] ?? '' }}" aria-label="Kredit baris {{ $i + 1 }}"></td><td><input name="lines[{{ $i }}][memo]" value="{{ $r['memo'] ?? '' }}" maxlength="200" aria-label="Memo baris {{ $i + 1 }}"></td></tr>
@endforeach
</tbody><tfoot><tr><td>Total</td><td class="num" id="td">Rp0</td><td class="num" id="tc">Rp0</td><td id="tb"></td></tr></tfoot></table></section>
<div class="actions"><button class="btn">Posting jurnal</button><a class="btn btn--ghost" href="{{ route('admin.adjustment-journals.index') }}">Batal</a></div>
</form>
@push('scripts')
<script>
(function () {
    var fmt = new Intl.NumberFormat('id-ID');
    function calc() {
        var d = 0, c = 0;
        document.querySelectorAll('.js-d').forEach(function (i) { d += +i.value || 0; });
        document.querySelectorAll('.js-c').forEach(function (i) { c += +i.value || 0; });
        document.getElementById('td').textContent = 'Rp' + fmt.format(d); document.getElementById('tc').textContent = 'Rp' + fmt.format(c);
        document.getElementById('tb').textContent = d === c ? (d ? 'Seimbang' : '') : 'Selisih Rp' + fmt.format(Math.abs(d - c));
    }
    document.getElementById('jl').addEventListener('input', calc); calc();
})();
</script>
@endpush
@endsection
