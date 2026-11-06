<h1>{{ $course->title }}</h1><p>{{ $progress }}</p>
<ol>@foreach ($course->lessons as $lesson)<li>{{ $lesson->title }} ({{ $lesson->minutes }} min){{ $lesson->done ? ' ✓' : '' }}</li>@endforeach</ol>
