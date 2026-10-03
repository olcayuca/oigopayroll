<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Assistant\SetupAssistant;
use App\Assistant\SetupContext;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kurulum asistanı: hidden without an API key, sends only setup context (no personal data).
 */
class SetupAssistantTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private Workplace $workplace;

    private User $owner;

    private FakeSetupAssistant $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->firm = Firm::factory()->create();
        $company = Company::factory()->for($this->firm)->create(['short_name' => 'Oigo Yazılım']);
        $this->workplace = Workplace::factory()->for($company)->create(['branch_name' => 'Merkez', 'nace_code' => null]);
        $this->owner = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->owner);

        $this->fake = new FakeSetupAssistant;
        $this->app->instance(SetupAssistant::class, $this->fake);
    }

    public function test_assistant_is_hidden_without_an_api_key(): void
    {
        config(['services.anthropic.key' => null]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-test="setup-assistant"', false);

        config(['services.anthropic.key' => 'test-key']);
        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="setup-assistant"', false)->assertSee('OigoAsistan')
            ->assertDontSee('data-test="assistant-not-configured"', false);
    }

    public function test_locally_the_assistant_is_shown_without_a_key_with_a_notice(): void
    {
        config(['services.anthropic.key' => null]);
        $this->app['env'] = 'local';

        $this->get(route('dashboard'))->assertOk()->assertSee('data-test="assistant-not-configured"', false);
        Livewire::test('setup-assistant')->call('ask', 'Merhaba')->assertSee('henüz etkinleştirilmedi');
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_question_is_answered_with_setup_context_and_kept_per_firm(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Employee::factory()->for($this->workplace)->create(['registry_no' => 'S-100', 'first_name' => 'Burak', 'last_name' => 'Yılmaz', 'mobile_phone' => '']);

        Livewire::test('setup-assistant')
            ->set('question', 'TCKN 12345678901 ve IBAN TR33 0006 1005 1978 6457 8413 26 nereye yazılır?')
            ->call('ask')
            ->assertSee('<strong>Kurulum › İşyerleri</strong> yolunu izleyin.', false)
            ->assertDontSee('12345678901')
            ->assertSet('question', '');

        $system = collect($this->fake->system)->pluck('text')->implode("\n");
        $this->assertStringContainsString('yalnızca kurulumdur', $system);
        $this->assertStringContainsString('Oigo Yazılım / Merkez', $system);
        $this->assertStringContainsString('NACE Kodu', $system);
        $this->assertStringContainsString('Sicil S-100: eksik', $system);
        $this->assertStringNotContainsString('Burak', $system, 'No personal names leave the server.');

        $question = $this->fake->messages[0]['content'];
        $this->assertStringNotContainsString('12345678901', $question);
        $this->assertStringNotContainsString('TR33', $question);

        // Follow-up questions carry the conversation.
        Livewire::test('setup-assistant')->call('ask', 'Peki personel?');
        $this->assertSame(['user', 'assistant', 'user'], array_column($this->fake->messages, 'role'));

        Livewire::test('setup-assistant')->call('restart')->assertDontSee('yolunu izleyin.');
    }

    public function test_context_only_contains_what_the_user_may_see(): void
    {
        $other = Workplace::factory()->for(Company::factory()->for($this->firm)->create(['short_name' => 'Oigo Lojistik']))->create(['branch_name' => 'Ankara']);
        $manager = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($manager, $this->workplace, [Permission::WorkplaceView]);

        $context = SetupContext::for($manager, $this->firm);

        $this->assertStringContainsString('Merkez', $context);
        $this->assertStringNotContainsString($other->branch_name, $context);
        $this->assertStringNotContainsString('Oigo Lojistik', $context);
    }

    public function test_requests_are_rate_limited(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $component = Livewire::test('setup-assistant');

        foreach (range(1, 21) as $i) {
            $component->call('ask', "Soru {$i}");
        }

        $this->assertSame(20, $this->fake->calls);
        $component->assertSee('çok fazla soru');
    }

    public function test_redacts_identifiers(): void
    {
        $this->assertSame('No: [11 haneli numara gizlendi]', SetupAssistant::redact('No: 12345678901'));
        $this->assertSame('[IBAN gizlendi]', SetupAssistant::redact('TR330006100519786457841326'));
        $this->assertSame('Sicil 1234', SetupAssistant::redact('Sicil 1234'));
    }
}

class FakeSetupAssistant extends SetupAssistant
{
    /** @var list<array{type: 'text', text: string, cacheControl?: array{type: 'ephemeral'}}> */
    public array $system = [];

    /** @var list<array{role: 'user'|'assistant', content: string}> */
    public array $messages = [];

    public int $calls = 0;

    protected function send(array $system, array $messages): string
    {
        $this->calls++;
        $this->system = $system;
        $this->messages = $messages;

        return '**Kurulum › İşyerleri** yolunu izleyin.';
    }
}
