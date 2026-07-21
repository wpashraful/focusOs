<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Leads base table
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->double('rating')->nullable();
            $table->integer('reviews_count')->default(0);
            $table->string('address')->nullable();
            $table->string('status')->default('Imported'); // Imported, Queued, Website Scanned, Email Found, AI Audited, Ready, Contacted, Interested, Won, Lost
            $table->integer('lead_score')->default(0);
            $table->string('source')->default('Google Maps');
            $table->timestamps();
        });

        // 2. Lead Socials (Decoupled Platforms: Facebook, Instagram, LinkedIn, etc.)
        Schema::create('lead_socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // facebook, instagram, linkedin, youtube, whatsapp, website_phone, contact_page_url, website_tech
            $table->string('url', 500);
            $table->timestamps();
        });

        // 3. Lead Audits (Strengths, Gaps, Suggestions, and pitches)
        Schema::create('lead_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->json('strengths')->nullable();
            $table->json('gaps')->nullable();
            $table->json('suggestions')->nullable();
            $table->text('cold_email_pitch')->nullable();
            $table->text('background')->nullable(); // RAW text scraped
            $table->timestamps();
        });

        // 4. Lead Email Drafts & Outreach records
        Schema::create('lead_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('draft'); // draft, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // 5. Lead Activities (Log flow)
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->string('activity_type'); // imported, queued, scanned, audited, email_drafted, email_sent, status_updated
            $table->text('description');
            $table->timestamps();
        });

        // 6. Lead Notes
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->text('content');
            $table->timestamps();
        });

        // 7. Workspace-level Integration Settings
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('key'); // e.g. openai_api_key, google_sheets_spreadsheet_id, etc.
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_emails');
        Schema::dropIfExists('lead_audits');
        Schema::dropIfExists('lead_socials');
        Schema::dropIfExists('leads');
    }
};
