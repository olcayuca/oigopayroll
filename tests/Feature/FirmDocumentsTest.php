<?php

namespace Tests\Feature;

use App\Actions\Access\GrantAccess;
use App\Actions\Firms\ManageFirmDocuments;
use App\Enums\AuditEvent;
use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FirmDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->firm = Firm::factory()->create(['name' => 'Belge Firması']);
        $this->owner = User::factory()->create();
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
    }

    private function upload(array $input = [], ?UploadedFile $file = null): FirmDocument
    {
        return app(ManageFirmDocuments::class)->store($this->firm, $file ?? UploadedFile::fake()->create('levha.pdf', 120, 'application/pdf'), [
            'type' => 'vergi_levhasi', 'title' => 'Vergi Levhası 2026', ...$input,
        ], $this->owner);
    }

    public function test_upload_stores_privately_and_validates(): void
    {
        $document = $this->upload(['valid_until' => today()->addDays(10)->toDateString()]);

        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith("firm-documents/{$this->firm->id}/", $document->path);
        $this->assertSame('levha.pdf', $document->original_name);
        $this->assertSame(FirmDocument::EXPIRING, $document->validity());
        $this->assertTrue(AuditLog::where('event', AuditEvent::DocumentUploaded)->exists());

        $this->expectException(ValidationException::class);
        $this->upload([], UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'));
    }

    public function test_company_must_belong_to_the_firm(): void
    {
        $foreign = Company::factory()->create();

        $this->expectException(ValidationException::class);
        $this->upload(['company_id' => $foreign->id]);
    }

    public function test_panel_page_upload_download_and_delete(): void
    {
        $this->actingAs($this->owner);
        $company = Company::factory()->for($this->firm)->create();

        $this->get(route('documents.index'))->assertOk()->assertSee('Belge Yükle');

        Livewire::test('firm-documents', ['firm' => $this->firm])
            ->call('create')
            ->set('form.type', 'imza_sirkuleri')
            ->assertSet('form.title', 'İmza Sirküleri')
            ->set('form.company_id', (string) $company->id)
            ->set('file', UploadedFile::fake()->image('sirku.png'))
            ->call('save')
            ->assertHasNoErrors();

        $document = FirmDocument::sole();
        $this->assertSame($company->id, $document->company_id);

        $this->get(route('documents.download', $document))->assertOk()->assertHeader('content-type', 'image/png');
        $this->assertTrue(AuditLog::where('event', AuditEvent::DocumentDownloaded)->exists());

        Livewire::test('firm-documents', ['firm' => $this->firm])->call('delete', $document->id);
        $this->assertSame(0, FirmDocument::count());
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_access_is_limited_to_the_firm_and_its_permissions(): void
    {
        $document = $this->upload();

        // Another firm's owner.
        $stranger = User::factory()->create();
        app(GrantAccess::class)->handle($stranger, Firm::factory()->create(), Permission::firmOwnerDefaults());
        $this->actingAs($stranger)->get(route('documents.download', $document))->assertForbidden();

        // A viewer of this firm can download but not manage.
        $viewer = User::factory()->create();
        app(GrantAccess::class)->handle($viewer, $this->firm, [Permission::FirmView]);
        $this->actingAs($viewer)->get(route('documents.download', $document))->assertOk();
        Livewire::test('firm-documents', ['firm' => $this->firm])->assertDontSee('Belge Yükle')->call('create')->assertForbidden();

        // A company-level user does not see firm documents.
        $companyUser = User::factory()->create();
        app(GrantAccess::class)->handle($companyUser, Company::factory()->for($this->firm)->create(), [Permission::CompanyView]);
        $this->actingAs($companyUser)->get(route('documents.download', $document))->assertForbidden();
        $this->get(route('documents.index'))->assertOk()->assertSee('Belgeleri görme yetkiniz yok');

        // Passive firms are read-only.
        $this->firm->update(['status' => FirmStatus::Passive]);
        $this->assertFalse($this->owner->fresh()?->can('manageDocuments', $this->firm->fresh()));
    }

    public function test_admin_firm_tab_and_tracking_page(): void
    {
        $this->upload(['title' => 'Eski Levha', 'valid_until' => today()->subDay()->toDateString()]);
        $this->upload(['title' => 'Yeni Levha', 'valid_until' => today()->addDays(5)->toDateString()]);
        $this->upload(['title' => 'Süresiz Sözleşme', 'type' => 'sozlesme']);

        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.firms.show', ['firm' => $this->firm, 'sekme' => 'belgeler']))->assertOk()->assertSee('Süresiz Sözleşme');

        $this->get(route('admin.documents.index'))->assertOk()->assertSee('role="tablist"', false)
            ->assertSee('Eski Levha')->assertDontSee('Yeni Levha');
        $this->get(route('admin.documents.index', ['sekme' => 'yaklasan']))->assertSee('Yeni Levha')->assertDontSee('Eski Levha');
        $this->get(route('admin.documents.index', ['sekme' => 'tumu']))->assertSee('Süresiz Sözleşme');
    }
}
