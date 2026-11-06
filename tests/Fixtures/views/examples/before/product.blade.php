<h1>{{ $product->name }}</h1><p>{{ number_format($product->price / 100, 2) }} XAF · {{ $product->stock > 0 ? $product->stock.' in stock' : 'out of stock' }}</p>
