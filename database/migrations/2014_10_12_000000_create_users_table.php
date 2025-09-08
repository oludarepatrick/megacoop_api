<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('address')->nullable();;
            $table->string('phone')->nullable();
            $table->enum('gender',['','male','female'])->nullable();
            $table->string('dob')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('passport')->nullable();
            $table->foreignId('state_id')->nullable()->constrained();
            $table->foreignId('city_id')->nullable()->constrained();
            $table->string('zip_code')->nullable();
            $table->rememberToken();
            $table->boolean('status')->default(true);
            $table->string('verify_email_token')->nullable();
            $table->boolean('verify_email_status')->default(false);
            $table->string('referral_code')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('role_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
