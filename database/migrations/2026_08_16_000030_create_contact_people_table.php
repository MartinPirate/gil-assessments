<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contact person is a person, not a string — they have a name, an email and
 * a phone number, and a business partner may know several of them.
 *
 * customers.contact_person_id names the one a document defaults to, which is
 * SAP B1's OCRD.CntctPrsn pointing into OCPR. Invoices keep their own snapshot
 * of the name, so normalising here cannot rewrite a document already issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'name']);
        });

        Schema::table('customers', function (Blueprint $table) {
            // Deliberately no cascade on this side. The contacts already go
            // when their customer does, and a second cascade path between the
            // same two tables is something SQL Server refuses outright.
            $table->foreignId('contact_person_id')->nullable()->constrained('contact_people');
        });

        $now = now();

        // Every name already recorded becomes a real contact row, so the
        // migration is not a data loss disguised as a schema change.
        $customers = DB::table('customers')
            ->whereNotNull('contact_person')
            ->where('contact_person', '<>', '')
            ->orderBy('id')
            ->get(['id', 'contact_person']);

        foreach ($customers as $customer) {
            $contactId = DB::table('contact_people')->insertGetId([
                'customer_id' => $customer->id,
                'name' => $customer->contact_person,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('customers')
                ->where('id', $customer->id)
                ->update(['contact_person_id' => $contactId]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('contact_person');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_person', 150)->nullable();
        });

        // Going back keeps the default contact's name; the emails and phone
        // numbers have nowhere to live in the old shape and are dropped.
        $customers = DB::table('customers')
            ->whereNotNull('contact_person_id')
            ->orderBy('id')
            ->get(['id', 'contact_person_id']);

        foreach ($customers as $customer) {
            DB::table('customers')
                ->where('id', $customer->id)
                ->update([
                    'contact_person' => DB::table('contact_people')
                        ->where('id', $customer->contact_person_id)
                        ->value('name'),
                ]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['contact_person_id']);
            $table->dropColumn('contact_person_id');
        });

        Schema::dropIfExists('contact_people');
    }
};
