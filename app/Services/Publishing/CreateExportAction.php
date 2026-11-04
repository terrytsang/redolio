<?php

namespace App\Services\Publishing;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\Export;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class CreateExportAction
{
    public function __construct(
        private readonly ProductionArtifactGenerator $generator,
        private readonly ArtifactValidator $validator,
    ) {}

    /**
     * @return array{export: Export, token: string}|array{export: null, token: null, errors: string[]}
     */
    public function execute(AnalysisRun $run, DesignSpec $spec, ?User $user): array
    {
        $workDir = storage_path('app/private/exports-tmp/'.Str::uuid());
        $this->generator->generate($run, $spec, $workDir);
        $validation = $this->validator->validate($workDir, $spec);

        if (! $validation['valid']) {
            File::deleteDirectory($workDir);

            return ['export' => null, 'token' => null, 'errors' => $validation['errors']];
        }

        $zipName = Str::uuid().'.zip';
        $zipRelativePath = "exports/{$zipName}";
        $zipAbsolutePath = storage_path("app/private/{$zipRelativePath}");
        File::ensureDirectoryExists(dirname($zipAbsolutePath));

        $this->zipDirectory($workDir, $zipAbsolutePath);
        File::deleteDirectory($workDir);

        $token = Str::random(48);

        $export = Export::create([
            'analysis_run_id' => $run->id,
            'user_id' => $user?->id,
            'design_spec_id' => $spec->id,
            'format' => 'static_html',
            'storage_path' => $zipRelativePath,
            'download_token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
        ]);

        return ['export' => $export, 'token' => $token];
    }

    private function zipDirectory(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (File::allFiles($sourceDir) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }

        $zip->close();
    }
}
