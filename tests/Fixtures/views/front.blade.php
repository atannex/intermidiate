<h1>{{ $year }}</h1>@foreach ($articles as $article)<li>{{ $article->title }} · {{ $article->writer->name }}</li>@endforeach{{ $articles->links() }}
