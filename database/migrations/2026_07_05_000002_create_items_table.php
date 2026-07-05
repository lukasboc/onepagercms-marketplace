<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // plugin | theme
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->string('purchase_url')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|delisted
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
