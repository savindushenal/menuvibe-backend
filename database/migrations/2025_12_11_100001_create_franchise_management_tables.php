<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Franchise custom pricing table
        if (!Schema::hasTable('franchise_pricing')) {
            Schema::create('franchise_pricing', function (Blueprint $table) {
                $table->id();
                $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
                $table->enum('pricing_type', ['fixed_yearly', 'pay_as_you_go', 'custom'])->default('pay_as_you_go');
                $table->decimal('yearly_price', 15, 2)->nullable(); // e.g., 7,200,000.00
                $table->decimal('per_branch_price', 12, 2)->nullable(); // e.g., 8,000.00 per branch per month
                $table->integer('initial_branches')->default(0); // Starting branches included
                $table->decimal('setup_fee', 12, 2)->default(0); // One-time setup fee
                $table->text('custom_terms')->nullable(); // Custom payment terms
                $table->date('contract_start_date')->nullable();
                $table->date('contract_end_date')->nullable();
                $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        // Franchise payments tracking table
        if (!Schema::hasTable('franchise_payments')) {
            Schema::create('franchise_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
                $table->foreignId('franchise_pricing_id')->nullable()->constrained('franchise_pricing')->onDelete('set null');
                $table->decimal('amount', 15, 2);
                $table->enum('payment_type', ['setup', 'monthly', 'quarterly', 'yearly', 'branch_addition', 'custom']);
                $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled', 'refunded'])->default('pending');
                $table->date('due_date');
                $table->date('paid_date')->nullable();
                $table->string('payment_method')->nullable(); // bank_transfer, card, cash, etc.
                $table->string('transaction_reference')->nullable();
                $table->text('notes')->nullable();
                $table->integer('branches_count')->nullable(); // Number of branches this payment covers
                $table->string('period_start')->nullable(); // Billing period start
                $table->string('period_end')->nullable(); // Billing period end
                $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        // Franchise branches table (locations within franchise)
        if (!Schema::hasTable('franchise_branches')) {
            Schema::create('franchise_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
                $table->foreignId('location_id')->nullable()->constrained()->onDelete('set null');
                $table->string('branch_name');
                $table->string('branch_code')->nullable(); // e.g., BR001, BR002
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_paid')->default(false); // Whether payment is current
                $table->date('activated_at')->nullable();
                $table->date('deactivated_at')->nullable();
                $table->foreignId('added_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        // Franchise invitations table
        if (!Schema::hasTable('franchise_invitations')) {
            Schema::create('franchise_invitations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
                $table->string('email');
                $table->string('name')->nullable();
                $table->enum('role', ['franchise_owner', 'franchise_manager', 'branch_manager', 'staff'])->default('branch_manager');
                $table->foreignId('branch_id')->nullable()->constrained('franchise_branches')->onDelete('cascade');
                $table->string('token', 64)->unique();
                $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->text('message')->nullable(); // Custom invitation message
                $table->boolean('send_credentials')->default(false); // Whether to auto-generate and send password
                $table->string('temp_password')->nullable(); // Temporary password (encrypted)
                $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        // Franchise accounts (users associated with franchise)
        if (!Schema::hasTable('franchise_accounts')) {
            Schema::create('franchise_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->enum('role', ['franchise_owner', 'franchise_manager', 'branch_manager', 'staff'])->default('staff');
                $table->foreignId('branch_id')->nullable()->constrained('franchise_branches')->onDelete('set null');
                $table->json('permissions')->nullable(); // Custom permissions
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                
                $table->unique(['franchise_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_accounts');
        Schema::dropIfExists('franchise_invitations');
        Schema::dropIfExists('franchise_branches');
        Schema::dropIfExists('franchise_payments');
        Schema::dropIfExists('franchise_pricing');
    }
};
