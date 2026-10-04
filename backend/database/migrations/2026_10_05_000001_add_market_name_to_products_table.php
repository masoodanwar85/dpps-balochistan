<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('market_name', 150)->nullable()->after('generic_name');
        });

        foreach (DB::table('products')->orderBy('id')->get() as $product) {
            $candidate = trim($product->generic_name.' '.$product->concentration.' '.$product->formulation);
            $marketName = mb_substr($candidate !== '' ? $candidate : 'Product '.$product->id, 0, 150);

            DB::table('products')->where('id', $product->id)->update([
                'market_name' => $marketName,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('market_name', 150)->nullable(false)->change();
            $table->unique('market_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['market_name']);
            $table->dropColumn('market_name');
        });
    }
};
