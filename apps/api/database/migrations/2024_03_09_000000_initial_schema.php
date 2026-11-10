<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('message');
            $table->string('status', 32)->default('open');
            $table->string('category', 32)->nullable();
            $table->double('confidence')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('request_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('body');
            $table->string('author_name', 120);
            $table->foreignUuid('request_id')->constrained('customer_requests')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_notes');
        Schema::dropIfExists('customer_requests');
    }
};
