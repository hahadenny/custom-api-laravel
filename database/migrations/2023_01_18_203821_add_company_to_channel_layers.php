<?php

use App\Models\ChannelLayer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('channel_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        $layers = ChannelLayer::withTrashed()->with('createdBy.company')->get();
        $layers->each(function($layer){
            /** @var ChannelLayer $layer */
            $layer->company_id = $layer->createdBy?->company?->id;
            $layer->save();
        });

        // Schema::table('channel_layers', function (Blueprint $table) {
        //     // make not null
        //     $table->unsignedBigInteger('company_id')->nullable(false)->change();
        // });
    }

    public function down()
    {
        Schema::table('channel_layers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
