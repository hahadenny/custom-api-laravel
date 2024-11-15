<?php

namespace App\Services\Engines\Avalanche;

use App\Services\Engines\Unreal\UnrealEvent;

class AvalancheEvent extends UnrealEvent
{
    /**
     * Avalanche.js > AvalancheEvents > continueAnim(channel, asset, avalancheChannel, batch)
     */
    public function continueAnim($channel, $asset, $avalancheChannel, $batch)
    {
        //     const request = {
        //         objectPath: "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
        //         functionName: "ContinueAvalancheAnimStatic",
        //         parameters: {
        //             Path: asset + "." + asset.match(/\/[^\/]*$/)[0].substr(1),
        //             Channel: avalancheChannel
        //         }
        //     }
        $assetMatch = $this->helper->findAssetMatch($asset);
        $request = [
            'objectPath'   => "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
            'functionName' => "ContinueAvalancheAnimStatic",
            "parameters" => [
                "Path" => $asset . "." . substr($assetMatch, 1),
                "Channel" => $avalancheChannel,
            ]
        ];
        // return this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
        return $this->addToBatch("/remote/object/call", "PUT", $request, $batch);
    }

    /**
     * Avalanche.js > AvalancheEvents > stopAsset(channel, asset, avalancheChannel, batch)
     */
    public function stopAsset($channel, $asset, $avalancheChannel, $batch)
    {
        //     const request = {
        //         objectPath: "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
        //         functionName: "StopAvalancheStatic",
        //         parameters: {
        //             Path: asset + "." + asset.match(/\/[^\/]*$/)[0].substr(1),
        //             Channel: avalancheChannel
        //         }
        //     }
        $assetMatch = $this->helper->findAssetMatch($asset);
        $request = [
            'objectPath'   => "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
            'functionName' => "StopAvalancheStatic",
            "parameters" => [
                "Path" => $asset . "." . substr($assetMatch, 1),
                "Channel" => $avalancheChannel,
            ]
        ];
        // return this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
        return $this->addToBatch("/remote/object/call", "PUT", $request, $batch);
    }

    // async sendSubmission(channel, asset, data, schema, avalancheChannel) {
    //     const preset = asset.replace("/Game", "/Temp/" + avalancheChannel).replaceAll("/", "%2F");
    //     super.sendSubmission(channel, preset, data, schema, null, new AvalancheBatch(this, asset, avalancheChannel));
    // }
    public function buildSubmission($channel, $data, $schema, ...$params)
    {
        // make sure we avoid undefined errors when destructuring in case
        // both $asset and $avalancheChannel were not sent
        [$asset, $avalancheChannel] = $params + [null, null];

        $preset = str_replace("/", "%2F", str_replace("/Game", "/Temp/", $asset));

        return parent::buildSubmission(
            $channel,
            $data,
            $schema,
            $preset,
            null, // $submitGroup
            new AvalancheBatch($this, $asset, $avalancheChannel), // $batch
            ...$params
        );
    }


    // --> NOT CURRENTLY USED IN JS
    // startAsset(channel, asset, avalancheChannel, batch) {
    //     const request = {
    //         objectPath: "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
    //         functionName: "StartAvalancheStatic",
    //         Async: true,
    //         parameters: {
    //             Path: asset + "." + asset.match(/\/[^\/]*$/)[0].substr(1),
    //             Channel: avalancheChannel
    //         }
    //     }
    //
    //     return this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
    // }


    // --> NOT CURRENTLY USED IN JS
    // continueOnChannel(channel, avalancheChannel, batch) {
    //     const request = {
    //         objectPath: "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
    //         functionName: "ContinueAllAnimsStatic",
    //         parameters: {
    //             Channel: avalancheChannel
    //         }
    //     }
    //
    //     return this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
    // }


    // --> NOT CURRENTLY USED IN JS
    // playAnims(channel, asset, avalancheChannel, batch) {
    //     const request = {
    //         objectPath: "/Script/PortaInterface.Default__PortaAvalancheSubsystem",
    //         functionName: "PlayAvalancheAnimsStatic",
    //         Async: true,
    //         parameters: {
    //             Path: asset + "." + asset.match(/\/[^\/]*$/)[0].substr(1),
    //             Channel: avalancheChannel
    //         }
    //     }
    //
    //     return this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
    // }
}
