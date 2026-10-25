<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Tests\Feature;

use Atannex\Intermidiate\Attributes\Config;
use Atannex\Intermidiate\Attributes\CurrentUser;
use Atannex\Intermidiate\Attributes\Old;
use Atannex\Intermidiate\Attributes\Session;
use Atannex\Intermidiate\Presenter;
use Atannex\Intermidiate\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

final class AmbientTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('/form', fn () => response()->json(FormPresenter::make()->toArray()));
        Route::middleware('web')->post('/form', fn () => redirect('/form')->withInput()->with('status', 'Check the form'));
    }

    public function test_config_locale_and_timezone_fill_themselves(): void
    {
        config(['app.name' => 'Atannex', 'app.timezone' => 'Africa/Douala']);
        app()->setLocale('fr');

        $presenter = SitePresenter::make();

        $this->assertSame('Atannex', $presenter->siteName);
        $this->assertSame('fr', $presenter->locale);
        $this->assertSame('Africa/Douala', $presenter->timezone);
        $this->assertSame('fallback', $presenter->missing, 'a missing config key keeps the default');
        $this->assertSame('Given', SitePresenter::make(siteName: 'Given')->siteName, 'passed data wins');
    }

    public function test_old_input_and_session_flash_after_a_failed_submit(): void
    {
        $this->post('/form', ['email' => 'ma@example.cm', 'full_name' => 'Ma Ngum']);

        $this->get('/form')->assertJson([
            'email' => 'ma@example.cm',
            'fullName' => 'Ma Ngum',
            'status' => 'Check the form',
        ]);
    }

    public function test_the_signed_in_user_by_attribute_or_by_name(): void
    {
        $this->assertNull(ViewerPresenter::make()->user, 'guests are null when nullable');

        $this->actingAs($ma = new GenericUser(['id' => 1, 'name' => 'Ma Ngum']));

        $presenter = ViewerPresenter::make();
        $this->assertSame($ma, $presenter->user);
        $this->assertSame($ma, $presenter->author);
    }

    public function test_parameters_with_a_default_keep_their_own_meaning(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));

        $presenter = FolioPresenter::make();

        $this->assertNull($presenter->timezone, 'its own fallback still runs');
        $this->assertNull($presenter->user, 'an author, not the viewer');
        $this->assertSame('en-GB', $presenter->locale);
    }

    public function test_a_required_user_sends_guests_to_log_in(): void
    {
        $this->expectException(AuthenticationException::class);

        AccountPresenter::make();
    }
}

final class SitePresenter extends Presenter
{
    public function __construct(
        #[Config('app.name')] public readonly string $siteName,
        public readonly string $locale,
        public readonly string $timezone,
        #[Config('nope.missing')] public readonly string $missing = 'fallback',
    ) {}
}

final class FormPresenter extends Presenter
{
    public function __construct(
        #[Old] public readonly ?string $email = null,
        #[Old] public readonly ?string $fullName = null,
        #[Session] public readonly ?string $status = null,
    ) {}
}

final class ViewerPresenter extends Presenter
{
    public function __construct(
        public readonly ?Authenticatable $user,
        #[CurrentUser] public readonly ?Authenticatable $author = null,
    ) {}
}

final class FolioPresenter extends Presenter
{
    public function __construct(
        public readonly ?string $timezone = null,
        // A concrete user class, as in an app (the container binds the Authenticatable contract itself).
        public readonly ?GenericUser $user = null,
        public readonly string $locale = 'en-GB',
    ) {}
}

final class AccountPresenter extends Presenter
{
    public function __construct(#[CurrentUser] public readonly Authenticatable $account) {}
}
