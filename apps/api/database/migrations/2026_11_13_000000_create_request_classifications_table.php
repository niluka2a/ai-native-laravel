<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('request_id')->constrained('customer_requests')->cascadeOnDelete();
            $table->string('category', 32);
            $table->double('confidence');
            $table->string('provider', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['category', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_classifications');
    }
};
