<?php

use App\Models\Channel;
use App\Models\ChannelLayer;
use App\Models\Company;
use App\Models\Project;
use App\Models\Schedule\ScheduleSet;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {

        Schema::create('schedule_sets', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->comment('Name safe to use as socket channel');
            $table->string('description')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('sort_order'); // order in own table grid of sets
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('channel_layers', function (Blueprint $table) {
            $table->foreignId('schedule_set_id')->nullable()->after('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        // copy layers data to new table
        $layers = ChannelLayer::withTrashed()->whereNotNull('channel_id')->orderBy('channel_id')->get();
        $order = 1;
        foreach($layers as $layer){
            // tie the ScheduleSet to the active project of the layer's creator
            $user = $layer->createdBy ?? null;
            $project = $user?->userProject ?? null;

            $set = ScheduleSet::where('id', $layer->channel_id)->first()
                ?? new ScheduleSet([
                    'name' => 'Schedule Set '.$order,
                ]);

            if(!$set->exists){
                // just copy the channel id so we can easily roll back
                $set->id = $layer->channel_id;
                $set->sort_order = $order;
            }
            if(isset($user)){
                $set->createdBy()->associate($user);
            }
            if(isset($project) && !empty(Project::find($project?->id))) {
                $set->project()->associate($project);
            }
            $set->save();

            $layer->scheduleSet()->associate($set);
            $layer->save();

            $order++;
        }

        Schema::table('channel_layers', function (Blueprint $table) {
            // todo: $table->dropConstrainedForeignId('channel_id');
        });
    }

    public function down()
    {
        /*// copy the channels back on rollback
        $layers = ChannelLayer::withTrashed()->get();

        Schema::table('channel_layers', function (Blueprint $table) {
            // $table->foreignId('channel_id')->nullable()->after('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::transaction(function() use ($layers) {
            foreach ($layers as $layer) {
                $layer->channel_id = $layer->schedule_set_id;
                $layer->save();
            }
        });*/

        Schema::table('channel_layers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_set_id');
        });

        Schema::dropIfExists('schedule_sets');
    }
};
