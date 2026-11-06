<x-examples.header :article="$article" />
<ul>@foreach ($more as $other)<li><a href="{{ $other->permalink }}">{{ $other->title }}</a> {{ $other->readingTime }}</li>@endforeach</ul>
