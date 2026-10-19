<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('chat-hybrid-ai.tables.conversations', 'chat_conversations'), function (Blueprint $table) {
            $table->string('visitor_id', 64)->nullable()->after('visitor_email')->index();
        });
    }

    public function down(): void
    {
        Schema::table(config('chat-hybrid-ai.tables.conversations', 'chat_conversations'), function (Blueprint $table) {
            $table->dropIndex(['visitor_id']);
            $table->dropColumn('visitor_id');
        });
    }
};
