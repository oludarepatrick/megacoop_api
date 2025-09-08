<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('signup_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('code', 8)->unique(); 
            $table->enum('status', ['available', 'used', 'expired'])
                  ->default('available');

            $table->foreignId('generated_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamp('expiration_date'); // 24 hrs expiry
            $table->timestamps();

            $table->index(['user_id', 'code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('signup_access_tokens');
    }
};
