<h1>{{ $period }}</h1>
@if ($lead)
<article class="lead">{{ $lead->title }}</article>
@endif
@foreach ($beside->entries() as $entry)
<article class="beside">{{ $entry->folio }} {{ $entry->title }}</article>
@endforeach
@foreach ($ledger->entries() as $entry)
<li class="ledger">{{ $entry->folio }} {{ $entry->item->title }}</li>
@endforeach
<nav class="calendar">
@foreach ($calendar as $month)
<a href="{{ $month->url }}" @class(['is-current' => $month->current, 'is-empty' => ! $month->filled])>{{ $month->short }}</a>
@endforeach
</nav>
<p class="pages">{{ $pages->fraction() }}</p>
<p class="vm">{{ $vm->period }}</p>
