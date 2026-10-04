<?php

use App\Contracts\Migration;
use App\Services\Installer\SeederService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Automatic AI feedback for filed PIREPs.
     *
     * One row per analysed PIREP. It lives in its own table instead of a column
     * on `pireps` so the feature can be dropped cleanly and a PIREP can be
     * re-analysed (deleting the row) without touching the core table.
     *
     * `severity` exists so staff can filter the ones that need attention:
     * 0 = correct, 1 = improvable, 2 = critical. It is the model's own
     * classification, not a re-derivation of the ACARS score.
     *
     * `pirep_id` is varchar(36) because that is the real type of `pireps.id` in
     * this install (it predates App\Contracts\Model::ID_MAX_LENGTH = 16, so the
     * constant cannot be used here without breaking the foreign key).
     */
    public function up(): void
    {
        if (!Schema::hasTable('pirep_ai_feedback')) {
            Schema::create('pirep_ai_feedback', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->string('pirep_id', 36);
                $table->string('model', 50);
                $table->string('verdict', 191)->nullable();
                $table->unsignedTinyInteger('severity')->default(0);
                $table->json('good_points')->nullable();
                $table->json('errors')->nullable();
                $table->text('action')->nullable();
                $table->text('summary')->nullable();

                $table->unsignedInteger('prompt_tokens')->nullable();
                $table->unsignedInteger('completion_tokens')->nullable();
                $table->unsignedInteger('total_tokens')->nullable();

                $table->timestamps();

                $table->unique('pirep_id');
                $table->index('severity');

                $table->foreign('pirep_id')
                    ->references('id')
                    ->on('pireps')
                    ->cascadeOnDelete();
            });
        }

        // Registers general.deepseek_api_key / _model / _feedback_enabled from
        // app/Database/seeds/settings.yml so they show up in Admin > Settings.
        // addSetting() skips keys that already exist, so this is idempotent.
        app(SeederService::class)->syncAllSettings();
    }
};
