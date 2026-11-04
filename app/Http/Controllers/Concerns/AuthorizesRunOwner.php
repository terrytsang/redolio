<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AnalysisRun;
use Illuminate\Http\Request;

/** Signed-in owner only. A run made before signing up (no owner yet) is claimed by its first signed-in editor, as the preview does. */
trait AuthorizesRunOwner
{
    private function own(Request $request, AnalysisRun $run): void
    {
        $user = $request->user() ?? abort(401, 'Please sign in.');
        if ($run->user_id !== null && $run->user_id !== $user->id) {
            abort(403, 'This project belongs to another account.');
        }
        $run->user_id === null && $run->update(['user_id' => $user->id]);
    }
}
