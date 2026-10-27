<div align="center">

<h1>Atannex Intermidiate</h1>

<p><strong>Typed presenters for Laravel applications.</strong></p>

<p>
  <a href="https://github.com/atannex/intermidiate/blob/main/composer.json"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&amp;logoColor=white" alt="PHP 8.2 or later"></a>
  <a href="https://github.com/atannex/intermidiate/blob/main/composer.json"><img src="https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?logo=laravel&amp;logoColor=white" alt="Laravel 11 and 12"></a>
  <a href="https://github.com/atannex/intermidiate/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-2EA44F" alt="MIT license"></a>
</p>

<p>
  <a href="https://packages.atannex.com">Documentation</a>
  &middot;
  <a href="https://github.com/atannex/intermidiate">Source Code</a>
  &middot;
  <a href="#contributing">Contributing</a>
</p>

</div>

## Introduction

[Atannex Intermidiate](https://packages.atannex.com) is a [Laravel](https://laravel.com/) package for preparing view data in small, typed [PHP](https://www.php.net/) classes. It moves presentation logic out of [Blade templates](https://laravel.com/framework/docs/blade), keeping controllers focused on request handling and templates focused on markup.

Presenters can be [returned from controllers](https://packages.atannex.com), [bound to views](https://packages.atannex.com), or [used directly as route actions](https://packages.atannex.com). [Automatic discovery and naming conventions](https://packages.atannex.com) connect presenters to their views, while [typed inputs](https://packages.atannex.com) can be resolved from view data, [route parameters](https://laravel.com/framework/docs/routing#route-parameters), [query strings](https://laravel.com/framework/docs/requests#retrieving-input-from-the-query-string), and Laravel's [service container](https://laravel.com/framework/docs/container).

## Capabilities

The package provides [collection partitioning](https://packages.atannex.com), [item numbering](https://packages.atannex.com), [calendar data](https://packages.atannex.com), and [pagination helpers](https://packages.atannex.com) for archives, listings, and dashboards. [Navigation menus](https://packages.atannex.com), [tabs](https://packages.atannex.com), and [active menu states](https://packages.atannex.com) centralize navigation logic before a view renders.

[Model presenters](https://packages.atannex.com) add reusable computed fields to [Eloquent models](https://laravel.com/framework/docs/eloquent) while preserving access to their underlying properties and relationships. [Attribute-based model fetching](https://packages.atannex.com), [pagination](https://laravel.com/framework/docs/pagination), [lazy evaluation](https://packages.atannex.com), [memoized computations](https://packages.atannex.com), and [cached members](https://packages.atannex.com) support reusable data preparation across views and requests.

[Formatting helpers](https://packages.atannex.com) handle localized dates, numbers, excerpts, and reading times. Built-in [metadata and SEO tools](https://packages.atannex.com) prepare page titles, canonical URLs, social sharing tags, and [JSON-LD](https://json-ld.org/) structured data. Presenters can also provide [JSON responses](https://laravel.com/framework/docs/responses#json-responses), allowing the same presentation layer to serve HTML pages and API clients.

## Official Documentation

Read the [official Atannex Intermidiate documentation](https://packages.atannex.com) for installation instructions, configuration options, presenter conventions, and complete usage examples.

## Local Development

Clone the repository and install its dependencies with [Composer](https://getcomposer.org/):

```bash
git clone https://github.com/atannex/intermidiate.git
cd intermidiate
composer install
```

Run the [PHPUnit](https://phpunit.de/) test suite:

```bash
composer test
```

## Contributing

Contributions are welcome through [GitHub pull requests](https://github.com/atannex/intermidiate/pulls). Describe the problem your change addresses, include tests for behavior changes, and run the test suite before submitting your contribution.

## License

Atannex Intermidiate is open-source software licensed under the [MIT license](https://github.com/atannex/intermidiate/blob/main/LICENSE).
