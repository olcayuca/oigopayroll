<?php

namespace Tests\Feature\Admin;

use App\Enums\Portal;
use App\Models\Setting;
use App\Models\User;
use App\Support\LandingContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebsitePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_shows_defaults_and_links_to_the_panel(): void
    {
        $this->get('https://oigopayroll.test/')
            ->assertOk()
            ->assertSee(LandingContent::defaults()['hero_title'])
            ->assertSee('https://panel.oigopayroll.test/register')
            ->assertSee('https://panel.oigopayroll.test/login');
    }

    public function test_admin_edits_landing_content(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.website.index'))->assertOk();

        Livewire::test('pages::admin.website.index')
            ->set('content.hero_title', 'Yeni Başlık')
            ->call('addService')
            ->call('save')
            ->assertHasErrors('content.services.3.title')
            ->set('content.services.3.title', 'Danışmanlık')
            ->call('moveService', 3, -1)
            ->call('removeService', 0)
            ->call('save')
            ->assertHasNoErrors();

        Setting::putMany(['contact.phone' => '0212 000 00 00']);

        $response = $this->get('https://oigopayroll.test/')->assertOk();
        $response->assertSee('Yeni Başlık')->assertSee('Danışmanlık')->assertSee('0212 000 00 00');
        $response->assertDontSee(LandingContent::defaults()['services'][0]['title']);
        $this->assertSame('Danışmanlık', LandingContent::get()['services'][1]['title']);
    }

    public function test_non_admins_cannot_edit_the_website(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create());

        Livewire::test('pages::admin.website.index')->assertForbidden();
    }
}
