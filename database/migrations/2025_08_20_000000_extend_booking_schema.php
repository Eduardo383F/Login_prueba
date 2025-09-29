<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) room_types: políticas
        Schema::table('room_types', function (Blueprint $t) {
            if (!Schema::hasColumn('room_types', 'cancellation_policy')) {
                $t->text('cancellation_policy')->nullable()->after('description');
            }
        });

        // 2) reservations: datos de contacto y peticiones
        Schema::table('reservations', function (Blueprint $t) {
            if (!Schema::hasColumn('reservations', 'phone')) $t->string('phone', 20)->nullable()->after('children');
            if (!Schema::hasColumn('reservations', 'address')) $t->text('address')->nullable()->after('phone');
            if (!Schema::hasColumn('reservations', 'special_requests')) $t->text('special_requests')->nullable()->after('address');
        });

        // 3) extras + pivote reservation_extra
        if (!Schema::hasTable('extras')) {
            Schema::create('extras', function (Blueprint $t) {
                $t->id();
                $t->string('name', 80)->unique();
                $t->text('description')->nullable();
                $t->decimal('price', 10, 2)->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('reservation_extra')) {
            Schema::create('reservation_extra', function (Blueprint $t) {
                $t->unsignedBigInteger('reservation_id');
                $t->unsignedBigInteger('extra_id');
                $t->integer('quantity')->default(1);
                $t->decimal('unit_price', 10, 2)->default(0);
                $t->decimal('total_price', 10, 2)->default(0);
                $t->primary(['reservation_id', 'extra_id']);
                $t->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnUpdate()->cascadeOnDelete();
                $t->foreign('extra_id')->references('id')->on('extras')->cascadeOnUpdate()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_extra');
        Schema::dropIfExists('extras');

        Schema::table('reservations', function (Blueprint $t) {
            if (Schema::hasColumn('reservations', 'special_requests')) $t->dropColumn('special_requests');
            if (Schema::hasColumn('reservations', 'address')) $t->dropColumn('address');
            if (Schema::hasColumn('reservations', 'phone')) $t->dropColumn('phone');
        });

        Schema::table('room_types', function (Blueprint $t) {
            if (Schema::hasColumn('room_types', 'cancellation_policy')) $t->dropColumn('cancellation_policy');
        });
    }
};
