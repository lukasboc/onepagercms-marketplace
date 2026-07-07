<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_version_id')->constrained('item_versions')->cascadeOnDelete();
            $table->foreignId('runner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('check', 20); // manifest|hooks|uninstall|malware|functionality
            $table->string('status', 10); // passed|warning|failed|skipped
            $table->json('findings');
            $table->timestamps();
            $table->unique(['item_version_id', 'check']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_checks');
    }
};
