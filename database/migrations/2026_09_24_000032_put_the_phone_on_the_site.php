<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * The site told visitors nothing but "Instagram". The owner's phone — the
 * same number on WhatsApp — now shows in the footer and the mobile menu, and
 * goes to Google with the shop's details. He changes it in admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (blank(Setting::get(Setting::CONTACT_PHONE))) {
            Setting::put(Setting::CONTACT_PHONE, '+994992308050');
        }
    }

    public function down(): void
    {
        Setting::put(Setting::CONTACT_PHONE, '');
    }
};
