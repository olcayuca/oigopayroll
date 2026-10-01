<?php

namespace App\Kvkk;

use App\Enums\AuditEvent;
use App\Enums\KvkkRequestStatus;
use App\Enums\KvkkRequestType;
use App\Models\KvkkRequest;
use App\Models\User;
use App\Notifications\KvkkRequestAnswered;
use App\Support\Audit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Data subject applications submitted through the system and answered by HRD.
 */
class DataRequests
{
    /** Open applications a user may have at once (spam guard). */
    public const MAX_OPEN_PER_USER = 5;

    /**
     * @param  array<string, mixed>  $input  type, message
     */
    public function submit(User $user, array $input): KvkkRequest
    {
        $data = Validator::make($input, [
            'type' => ['required', Rule::enum(KvkkRequestType::class)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ], [], ['type' => 'Başvuru konusu', 'message' => 'Açıklama'])->validate();

        $open = KvkkRequest::query()->where('user_id', $user->id)
            ->whereIn('status', [KvkkRequestStatus::Open, KvkkRequestStatus::InProgress])->count();

        if ($open >= self::MAX_OPEN_PER_USER) {
            throw ValidationException::withMessages(['message' => 'Sonuçlanmamış '.self::MAX_OPEN_PER_USER.' başvurunuz var; yenisini bunlar yanıtlandıktan sonra oluşturabilirsiniz.']);
        }

        $request = KvkkRequest::create([
            'user_id' => $user->id,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'type' => $data['type'],
            'status' => KvkkRequestStatus::Open,
            'message' => $data['message'],
            'due_at' => today()->addDays(KvkkRequest::ANSWER_DAYS),
        ]);

        Audit::log(AuditEvent::KvkkRequestCreated, "KVKK başvurusu #{$request->id}: {$request->type->shortLabel()}", $request, [], $user);

        return $request;
    }

    /**
     * @param  array<string, mixed>  $input  status, response
     */
    public function update(KvkkRequest $request, array $input, User $actor): KvkkRequest
    {
        $closing = KvkkRequestStatus::tryFrom((string) ($input['status'] ?? ''))?->isClosed() ?? false;

        $data = Validator::make($input, [
            'status' => ['required', Rule::enum(KvkkRequestStatus::class)],
            'response' => [$closing ? 'required' : 'nullable', 'string', 'max:5000'],
        ], ['response.required' => 'Başvuruyu sonuçlandırırken yanıt yazılmalıdır.'], ['status' => 'Durum', 'response' => 'Yanıt'])->validate();

        $status = KvkkRequestStatus::from($data['status']);
        $wasClosed = $request->status->isClosed();

        $request->fill([
            'status' => $status,
            'response' => $data['response'] ?? null,
            'handled_by' => $actor->id,
            'resolved_at' => $status->isClosed() ? ($request->resolved_at ?? now()) : null,
        ])->save();

        Audit::log(AuditEvent::KvkkRequestUpdated, "KVKK başvurusu #{$request->id}: {$status->label()}", $request, [], $actor);

        if ($status->isClosed() && ! $wasClosed && $request->user !== null) {
            $request->user->notify(new KvkkRequestAnswered($request));
        }

        return $request;
    }
}
