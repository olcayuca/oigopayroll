<?php

namespace App\Kvkk;

use App\Enums\AuditEvent;
use App\Enums\PolicyType;
use App\Models\Consent;
use App\Models\PolicyDocument;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * KVKK texts and user decisions.
 *
 * Everyone signed in must decide on the current version of every published text:
 * the aydınlatma metni is acknowledged (mandatory), the açık rıza metni may be accepted or refused
 * (consent must be freely given, so refusing does not block the user) and withdrawn later.
 */
class Policies
{
    public function current(PolicyType $type): ?PolicyDocument
    {
        return PolicyDocument::query()
            ->where('type', $type)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Current versions of all published texts, keyed by type value.
     *
     * @return Collection<string, PolicyDocument>
     */
    public function currentDocuments(): Collection
    {
        return collect(PolicyType::cases())
            ->mapWithKeys(fn (PolicyType $type) => [$type->value => $this->current($type)])
            ->filter();
    }

    /**
     * Current texts the user has not decided on yet.
     *
     * @return Collection<string, PolicyDocument>
     */
    public function pendingFor(User $user): Collection
    {
        $documents = $this->currentDocuments();

        if ($documents->isEmpty()) {
            return $documents;
        }

        $decided = Consent::query()
            ->where('user_id', $user->id)
            ->whereIn('policy_document_id', $documents->pluck('id'))
            ->get()
            ->keyBy('policy_document_id');

        return $documents->reject(function (PolicyDocument $document) use ($decided) {
            $consent = $decided->get($document->id);

            // A mandatory text is only settled once it was acknowledged.
            return $consent !== null && ($consent->accepted || ! $document->type->isMandatory());
        });
    }

    /**
     * The user's decision on a document, if any.
     */
    public function decision(User $user, PolicyDocument $document): ?Consent
    {
        return Consent::query()->where('user_id', $user->id)->where('policy_document_id', $document->id)->first();
    }

    public function record(User $user, PolicyDocument $document, bool $accepted, ?Request $request = null): Consent
    {
        if ($document->published_at === null) {
            throw ValidationException::withMessages(['document' => 'Bu metin yayımlanmamış.']);
        }

        if (! $accepted && $document->type->isMandatory()) {
            throw ValidationException::withMessages(['document' => "{$document->type->label()} okunduğu onaylanmadan sistem kullanılamaz."]);
        }

        $request ??= request();

        $consent = Consent::updateOrCreate(
            ['user_id' => $user->id, 'policy_document_id' => $document->id],
            [
                'accepted' => $accepted,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
                'decided_at' => now(),
                'revoked_at' => null,
            ],
        );

        Audit::log(
            $accepted ? AuditEvent::ConsentGiven : AuditEvent::ConsentRefused,
            "{$document->type->label()} v{$document->version} ".($accepted ? 'onaylandı' : 'reddedildi'),
            $document,
            [],
            $user,
        );

        return $consent;
    }

    /**
     * Withdraw an explicit consent (KVKK md. 11 / 5): effective from now, the record is kept as evidence.
     */
    public function revoke(User $user, PolicyDocument $document): void
    {
        if ($document->type->isMandatory()) {
            throw ValidationException::withMessages(['document' => 'Aydınlatma metni geri alınamaz; yalnızca açık rıza geri alınabilir.']);
        }

        $consent = $this->decision($user, $document);

        if ($consent === null || ! $consent->isGiven()) {
            return;
        }

        $consent->forceFill(['revoked_at' => now()])->save();

        Audit::log(AuditEvent::ConsentRevoked, "{$document->type->label()} v{$document->version} rızası geri alındı", $document, [], $user);
    }

    /**
     * Publish a new version; every user is asked again.
     */
    public function publish(PolicyType $type, string $title, string $body, ?User $by = null): PolicyDocument
    {
        $title = trim($title);
        $body = trim($body);

        if ($title === '' || $body === '') {
            throw ValidationException::withMessages(['body' => 'Başlık ve metin boş olamaz.']);
        }

        $document = DB::transaction(function () use ($type, $title, $body, $by) {
            $version = (int) PolicyDocument::query()->where('type', $type)->lockForUpdate()->max('version') + 1;

            return PolicyDocument::create([
                'type' => $type,
                'version' => $version,
                'title' => mb_substr($title, 0, 255),
                'body' => $body,
                'published_at' => now(),
                'created_by' => $by?->id,
            ]);
        });

        Audit::log(AuditEvent::PolicyPublished, "{$type->label()} v{$document->version} yayımlandı", $document, [], $by);

        return $document;
    }
}
