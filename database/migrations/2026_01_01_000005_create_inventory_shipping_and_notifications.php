<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->integer('quantity_change');
            $table->unsignedInteger('quantity_after');
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->json('postal_codes')->nullable();
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('free_shipping_threshold', 12, 2)->nullable();
            $table->unsignedInteger('estimated_min_days')->default(2);
            $table->unsignedInteger('estimated_max_days')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'city']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('shipping_zone_id')->nullable()->after('coupon_id')->constrained('shipping_zones')->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('shipping_zone_id');
        });

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('inventory_movements');
    }
};
