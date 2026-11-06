<h1>{{ $course->title }}</h1><p>{{ $course->progress }}</p>
<ol>@foreach ($course->lessons as $lesson)<li>{{ $lesson->label }}</li>@endforeach</ol>
