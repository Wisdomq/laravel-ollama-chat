<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Activate all existing workflow templates so they appear in the sidebar
        DB::table('workflows')->update(['is_active' => true]);
    }

    public function down(): void
    {
        // Only keep image active on rollback
        DB::table('workflows')->where('type', '!=', 'image')->update(['is_active' => false]);
    }
};