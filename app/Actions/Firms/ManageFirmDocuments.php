<?php

namespace App\Actions\Firms;

use App\Enums\AuditEvent;
use App\Enums\DocumentType;
use App\Models\Firm;
use App\Models\FirmDocument;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Upload, edit and delete firm documents. Authorization is the caller's job (FirmPolicy::manageDocuments).
 */
class ManageFirmDocuments
{
    public const DISK = 'local';

    public const MAX_KB = 10240;

    /**
     * @param  array<string, mixed>  $input  type, title, company_id, valid_until, notes
     */
    public function store(Firm $firm, ?UploadedFile $file, array $input, ?User $user = null): FirmDocument
    {
        $data = Validator::make([...$input, 'file' => $file], [
            ...$this->rules($firm),
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KB],
        ], [], self::attributes())->validate();

        if ($file === null) {
            throw ValidationException::withMessages(['file' => 'Dosya seçilmedi.']);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs("firm-documents/{$firm->id}", Str::uuid().'.'.$extension, self::DISK);

        $document = $firm->documents()->create([
            'company_id' => $data['company_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'disk' => self::DISK,
            'path' => (string) $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'uploaded_by' => $user?->id,
        ]);

        Audit::log(AuditEvent::DocumentUploaded, "{$firm->name}: belge yüklendi ({$document->type->label()} · {$document->title})", $firm, ['document_id' => $document->id], $user, scope: $document);

        return $document;
    }

    /**
     * Change the details; the file itself is replaced by uploading a new document.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(FirmDocument $document, array $input, ?User $user = null): FirmDocument
    {
        $data = Validator::make($input, $this->rules($document->firm), [], self::attributes())->validate();

        $document->update([
            'company_id' => $data['company_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($document->wasChanged('valid_until')) {
            $document->forceFill(['expiry_notice' => null])->save();
        }

        return $document;
    }

    public function delete(FirmDocument $document, ?User $user = null): void
    {
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        Audit::log(AuditEvent::DocumentDeleted, "{$document->firm->name}: belge silindi ({$document->type->label()} · {$document->title})", $document->firm, ['document_id' => $document->id], $user, scope: $document);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(Firm $firm): array
    {
        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('firm_id', $firm->id)->whereNull('deleted_at')],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'type' => 'Belge türü',
            'title' => 'Başlık',
            'company_id' => 'Şirket',
            'valid_until' => 'Geçerlilik tarihi',
            'notes' => 'Not',
            'file' => 'Dosya',
        ];
    }
}
