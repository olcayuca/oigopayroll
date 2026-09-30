<?php

namespace App\Http\Controllers;

use App\Enums\PolicyType;
use App\Kvkk\Policies;
use App\Models\PolicyDocument;
use Illuminate\Contracts\View\View;

/**
 * Public page with the current version of a KVKK text.
 */
class ShowPolicyDocument extends Controller
{
    public function __invoke(string $type, Policies $policies): View
    {
        $document = $policies->current(PolicyType::from($type));

        abort_if(! $document instanceof PolicyDocument, 404);

        return view('kvkk.document', ['document' => $document]);
    }
}
