<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->string('zip_path')->nullable(); // null once rejected/cleaned; paid zips are review copies only
            $table->text('changelog')->nullable();
            $table->string('requires_opcms', 20)->nullable();
            $table->string('requires_php', 20)->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->timestamps();
            $table->unique(['item_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_versions');
    }
};
