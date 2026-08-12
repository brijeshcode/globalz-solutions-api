<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('folder');
            $table->unsignedTinyInteger('month')->nullable()->after('year');
            $table->string('module')->nullable()->after('month');

            $table->index(['module', 'year', 'month']);
        });

        // Backfill existing rows from their stored file_path
        // (documents/{tenant}/{year}/{month}/{module}/{file}).
        DB::table('documents')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $parts = Document::pathParts($row->file_path);

                DB::table('documents')->where('id', $row->id)->update([
                    'year'   => $parts['year'],
                    'month'  => $parts['month'],
                    'module' => $parts['module'],
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['module', 'year', 'month']);
            $table->dropColumn(['year', 'month', 'module']);
        });
    }
};
