<?php

use App\Support\EmailContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')
            ->select(['id', 'body_html'])
            ->orderBy('id')
            ->get()
            ->each(function (object $template): void {
                $normalized = EmailContent::sanitizeHtml((string) $template->body_html);

                if ($normalized === (string) $template->body_html) {
                    return;
                }

                DB::table('email_templates')
                    ->where('id', $template->id)
                    ->update([
                        'body_html' => $normalized,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
    }
};
