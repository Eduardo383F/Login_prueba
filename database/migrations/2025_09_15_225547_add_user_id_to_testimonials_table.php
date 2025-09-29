<?php
// database/migrations/XXXX_XX_XX_XXXXXX_add_user_id_to_testimonials_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('testimonials', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('testimonials', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
        });
    }
};
