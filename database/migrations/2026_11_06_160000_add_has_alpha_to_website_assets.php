<?php

use App\Support\ImageAlpha;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        // A picture with see-through areas (a logo, a slogan, a cut-out) must be shown as it is: no frame colour behind it, never cropped.
        Schema::table('website_assets', function (Blueprint $table) {
            $table->boolean('has_alpha')->default(false)->after('mime_type');
        });

        $disk = Storage::disk('public');
        DB::table('website_assets')->whereIn('mime_type', ['image/png', 'image/webp'])->whereNotNull('stored_path')->orderBy('id')->each(function ($a) use ($disk) {
            if ($disk->exists($a->stored_path) && ImageAlpha::fileHasTransparency($disk->path($a->stored_path))) {
                DB::table('website_assets')->where('id', $a->id)->update(['has_alpha' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('website_assets', fn (Blueprint $t) => $t->dropColumn('has_alpha'));
    }
};
