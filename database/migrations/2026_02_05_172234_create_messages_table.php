<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['text', 'image', 'video', 'pdf', 'excel', 'file'])->default('text');
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();

            $table->foreignId('reply_to')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('forwarded_from')->nullable()->constrained('messages')->nullOnDelete();

            $table->boolean('is_delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->boolean('is_seen')->default(false);
            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            $table->index(['chat_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
