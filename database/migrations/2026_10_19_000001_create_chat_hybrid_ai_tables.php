<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-hybrid-ai.tables.conversations', 'chat_conversations'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token', 64)->unique();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->string('mode', 20)->default('ai')->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamp('visitor_last_read_at')->nullable();
            $table->timestamp('admin_last_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create(config('chat-hybrid-ai.tables.messages', 'chat_messages'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained(config('chat-hybrid-ai.tables.conversations', 'chat_conversations'))
                ->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-hybrid-ai.tables.messages', 'chat_messages'));
        Schema::dropIfExists(config('chat-hybrid-ai.tables.conversations', 'chat_conversations'));
    }
};
