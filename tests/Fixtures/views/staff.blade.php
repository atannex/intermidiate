<p>{{ $lead->initials }} {{ $lead->name }}</p>
@foreach ($ledger->entries() as $entry)
<li>{{ $entry->folio }} {{ $entry->item->initials }}</li>
@endforeach
@foreach ($authors as $author)<a href="{{ route('authors.show', $author) }}">{{ $author->initials }}</a>@endforeach
{{ $authors->total() }}
