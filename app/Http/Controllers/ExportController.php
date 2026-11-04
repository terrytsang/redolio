<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\Export;
use App\Services\Billing\ExportPassAllowance;
use App\Services\Publishing\CreateExportAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function store(Request $request, AnalysisRun $analysisRun, CreateExportAction $createExportAction, ExportPassAllowance $allowance): RedirectResponse
    {
        $user = $request->user();
        if ($analysisRun->user_id !== null && $analysisRun->user_id !== $user->id) {
            abort(403, 'This project belongs to another account.');
        }

        if (! $allowance->canExport($user, $analysisRun)) {
            // Someone who has already bought a pass has used what it includes; everyone else needs a plan or a pass first.
            return $user->oneTimePurchases()->where('purchase_type', 'export_pass')->where('status', 'paid')->exists()
                ? redirect()->route('analyses.upgrade', $analysisRun->public_id)->with('status', 'You’ve used all the downloads included with this website’s Export Pass. Choose Starter or Agency for unlimited downloads and edits, or get another Export Pass.')
                : redirect()->route('pricing')->with('status', 'Code export is available on the Starter and Agency plans, or with an Export Pass.');
        }

        $spec = $analysisRun->currentDesignSpec();
        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        $result = $createExportAction->execute($analysisRun, $spec, $user);

        if (! $result['export']) {
            return redirect()->route('analyses.launch', $analysisRun->public_id)
                ->with('status', 'Export failed validation: '.implode(' ', $result['errors']));
        }

        $allowance->consumeExport($user, $analysisRun);

        // A download response leaves the person on the same page with no message, so send them to a page that starts the download
        // and shows how to put the files on their own web hosting.
        return redirect()->route('exports.ready', $result['token']);
    }

    /** "Your files are ready": starts the download and explains how to upload them to ordinary web hosting. */
    public function ready(string $token): \Illuminate\Contracts\View\View|RedirectResponse
    {
        $export = Export::where('download_token_hash', hash('sha256', $token))->first();

        if (! $export || $export->isExpired()) {
            return redirect()->route('landing')->with('status', 'This download link is invalid or has expired.');
        }

        return view('exports.ready', [
            'token' => $token,
            'export' => $export,
            'host' => parse_url((string) $export->analysisRun?->normalized_url, PHP_URL_HOST) ?: 'your website',
            'run' => $export->analysisRun,
        ]);
    }

    public function download(string $token): BinaryFileResponse|RedirectResponse
    {
        $export = Export::where('download_token_hash', hash('sha256', $token))->first();

        if (! $export || $export->isExpired()) {
            return redirect()->route('landing')->with('status', 'This download link is invalid or has expired.');
        }

        $path = Storage::disk('local')->path($export->storage_path);

        $export->update(['downloaded_at' => now()]);

        return response()->download($path, 'redo-export.zip');
    }
}
