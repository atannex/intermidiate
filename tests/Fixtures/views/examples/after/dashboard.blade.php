<p>{{ $summary }}</p>
<ul>@foreach ($courses as $course)<li>{{ $course->title }}: {{ $course->progress }}</li>@endforeach</ul>
