<x-before-header :article="$article" />
<ul>@foreach ($article->writer->articles as $other)<li><a href="{{ url('/articles/'.$other->id) }}">{{ $other->title }}</a> {{ max(1, (int) ceil(str_word_count(strip_tags($other->body)) / 200)) }} min read</li>@endforeach</ul>
