<?php

namespace App\Services\Engines\Unreal;

use App\Services\Engines\EngineBatch;
use App\Services\Engines\EngineEvent;
use Illuminate\Support\Facades\Log;

class UnrealEvent extends EngineEvent
{
    public const UEFuncArgSuffix     = "functionArguments";
    public const UESequenceNodeName  = "__PlaySequences";
    public const UESendToChannelName = "__SendToChannel";
    public const UELoadLevelsName    = "__LoadLevels";

    public function __construct(
        protected string $namespace = 'unreal', // default namespace
    )
    {
        parent::__construct(namespace: $this->namespace);
    }

    /**
     * UnrealEngine.js > UEEvents > sendSubmission(channel, preset, data, schema, submitGroup = null, batch = null)
     */
    public function buildSubmission($channel, $data, $schema, ...$params)
    {
        // make sure we avoid undefined errors when destructuring in case
        // preset, submitGroup, and batch were not all sent
        [$preset, $submitGroup, $batch] = $params + [null, null, null];

        if ( !$data) {
            return;
        }

        $batch ??= new UnrealBatch($this);

        // todo: validate/filter/populate data
        $data = $this->checkIpsumUEProperties($data, $schema);

        // deprecated? // const fileNodes = FormioHelper.findNodesOfType(schema, 'file').map(el => el.key);

        $properties = $this->schemaHelper->getPropertiesFromSchema($schema);
        $functions = $this->schemaHelper->getFunctionsFromSchema($schema, self::UEFuncArgSuffix);
        $mediaNodes = collect($this->schemaHelper->findNodesOfType($schema, "media_manager"))->map(fn($el) => $el['key'])->all();
        $imageNodeData = $this->schemaHelper->findNodesWithPred($schema, function ($node) use ($data) {
            return (!is_array($data) && !is_object($data) && isset($node[$data])
                && isset($node[$data]['uetype']) && $node[$data]['uetype'] === "/Script/Engine.Texture2D");
        });
        $imageNodes = array_map(function($imageNode){
            return $imageNode['key'];
        }, $imageNodeData);

        $concatNodeData = $this->schemaHelper->findNodesWithPred($schema, function($node) {
            return !empty($node['concatSelect']);
        });
        $concatNodes = array_map(function($concatNodeData){
            return $concatNodeData['key'];
        }, $concatNodeData);

        $submission = [];

        foreach ($properties as $prop) {
            // @todo !! --> checkSubmitGroup
            //  NOTE: the blank imageURL field is likely being sent as part of the message because
            //  checkSubmitGroup and the following checks are not done properly yet
            if ($this->checkSubmitGroup($schema, $submitGroup, $prop)) {

                // ?? deprecated?
                // if (fileNodes.includes(prop)) {
                //     if (data[prop] && data[prop].length > 0) {
                //         //const url = data[prop][0].url; // commented out in JS
                //     }
                // }

                if (!is_array($prop) && !is_object($prop) && in_array($prop, $imageNodes)) {
                    $this->setTextureAsset($channel, $preset, $prop, $data[$prop]);
                }
                elseif (!is_array($prop) && !is_object($prop) && in_array($prop, $mediaNodes)
                    && isset($data[$prop]) && isset($data[$prop]['url'])) {
                    //     let mediaExt = data[prop].url && data[prop].url.substr(data[prop].url.lastIndexOf(".") + 1).toLowerCase();
                    $mediaExt = $this->helper->getExtFromUrl($data[$prop]['url']);
                    switch ($mediaExt) {
                        case "jpg":
                        case "jpeg":
                        case "png":
                        case "bmp":
                        case "tiff":
                        case "tif":
                            // this.sendDownloadAndSetTexture(null, preset, prop, data[prop].url, batch);
                            $batch = $this->sendDownloadAndSetTexture(null, $preset, $prop, $data[$prop]['url'], $batch);
                            break;
                        case "mp4":
                        case "mpeg":
                        case "mpg":
                        case "avi":
                        case "mov":
                        case "m4v":
                            // this.sendDownloadAndPlayVideo(null, preset, prop, data[prop].url, batch);
                            $batch = $this->sendDownloadAndPlayVideo(null, $preset, $prop, $data[$prop]['url'], $batch);
                            break;
                        default:
                            break;
                    }
                }
                elseif (!is_array($prop) && !is_object($prop) && in_array($prop, $concatNodes) && isset($data[$prop]) && is_array($data[$prop])) {
                    // NOTE: JS Array.find() returns the first element in the array that matches, undefined if none
                    $node = collect($concatNodeData)->first(fn($item) => $item['key'] === $prop);
                    if (!empty($node)) {
                        $delim = $node['concatDelim'] ?? '';
                        $val = "";
                        foreach($data[$prop] as $str){
                            $val .= (!empty($val) ? $delim . $str : $str);
                        }
                        $submission[$prop] = $val;
                    }
                }
                elseif ( !is_array($prop) && !is_object($prop) && isset($data[$prop])) {
                    $submission[$prop] = $data[$prop];
                }
            }
        } // end foreach $properties

        // this.sendPropertyValues(null, preset, submission, batch);
        $batch = $this->preparePropertyValues($preset, $submission, $batch);

        // for (let func of functions) {
        //     if (data[func] && data[func][UEFuncArgSuffix] && data[func][UEFuncArgSuffix].paramlessCall !== false
        //         && this.checkSubmitGroup(schema, submitGroup, func)
        //     ) {
        //         const args = this.GetFunctionArgsFromSubmission(func, data);
        //         this.RunFunction(null, preset, func, args, batch);
        //     }
        // }
        foreach ($this->funcToNestedArray($functions) as $func => $function) {
            if (isset($data[$func]) && isset($data[$func][self::UEFuncArgSuffix]) && isset($data[$func][self::UEFuncArgSuffix]['paramlessCall']) && $data[$func][self::UEFuncArgSuffix]['paramlessCall'] !== false
                && $this->checkSubmitGroup($schema, $submitGroup, $func)
            ) {
                Log::debug(" ~~ CALLING \$func: $func");
                $args = $this->getFunctionArgsFromSubmission($func, $data);
                $batch = $this->runFunction(null, $preset, $func, $args, $batch);
            }
        }

        // if (data[UESequenceNodeName] && this.checkSubmitGroup(schema, submitGroup, UESequenceNodeName)) {
        //     const sequences = data[UESequenceNodeName].map((el) => el.Path);
        //     this.PlayLevelSequences(channel, sequences, batch);
        // }
        if (isset($data[self::UESequenceNodeName]) && $this->checkSubmitGroup($schema, $submitGroup, self::UESequenceNodeName)) {
            Log::debug(" -- sequences " . print_r($data[self::UESequenceNodeName], true));
            $sequences = collect($data[self::UESequenceNodeName])->map(fn($el) => $el['Path'])->all();
            $batch = $this->playLevelSequences($channel, $sequences, $batch);
        }


        /* COMMENT FROM JS: check for null rather than empty because if the field exists but has nothing
            we want it to call this so it can unload the level */
        // if (data[UELoadLevelsName] != null && this.checkSubmitGroup(schema, submitGroup, UELoadLevelsName)) {
        //     this.LoadStreamingLevel(null, data[UELoadLevelsName], batch);
        // }
        if (isset($data[self::UELoadLevelsName]) && $this->checkSubmitGroup($schema, $submitGroup, self::UELoadLevelsName)){
            $batch = $this->loadStreamingLevel(null, $data[self::UELoadLevelsName], $batch);
        }

        // return batch.send(channel);
        return $batch->prepareToSend($channel);
    }

    /**
     * COMMENT FROM JS: this call is probably temporary and should be used just for testing
     *
     * UnrealEngine.js > UEEvents > sendDownloadAndSetTexture(channel, preset, prop, url, batch)
     */
    public function sendDownloadAndSetTexture($channel, $preset, $prop, $url, $batch) : EngineBatch
    {
        $request = [
            'objectPath'   => "/Script/PortaInterface.Default__PortaAssetSubsystem",
            'functionName' => "DownloadAndSetTextureFromPresetStatic",
            'parameters'   => [
                "PresetName" => $preset,
                "PropertyName" => $prop,
                "Url" => $url
            ],
        ];
        // return this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
        return $this->addToBatch("/remote/object/call", "PUT", $request, $batch);
    }

    /**
     * COMMENT FROM JS: this call is probably temporary and should be used just for testing
     *
     * UnrealEngine.js > UEEvents > sendDownloadAndPlayVideo(channel, preset, prop, url, batch)
     */
    public function sendDownloadAndPlayVideo($channel, $preset, $prop, $url, $batch) : EngineBatch
    {
        $request = [
            'objectPath'   => "/Script/PortaInterface.Default__PortaAssetSubsystem",
            'functionName' => "DownloadAndPlayVideoOnPresetStatic",
            'parameters'   => [
                "PresetName" => $preset,
                "PropertyName" => $prop,
                "Url" => $url
            ],
        ];
        // return this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
        return $this->addToBatch("/remote/object/call", "PUT", $request, $batch);
    }

    /**
     * Split function strings into nested arrays. Example:
     *      'Ticker Live Logo Fly Out (BP_NAB_Demo_Controller).functionArguments.paramlessCall'
     *
     * @param string[] $functions
     */
    protected function funcToNestedArray(array $functions, string $separator = '.') : array
    {
        $nested_array = [];

        foreach ($functions as $func_str) {
            $temp = &$nested_array;
            foreach (explode($separator, $func_str) as $key) {
                $temp = &$temp[$key];
            }
            $temp = [];
        }

        return $nested_array;
    }

    /**
     * UnrealEngine.js > UEEvents > PlayLevelSequences(channel, sequences, batch)
     */
    protected function playLevelSequences($channel, $sequences, $batch) : EngineBatch
    {
        if ( !$sequences || sizeof($sequences) === 0) {
            return $batch;
        }

        // COMMENT FROM JS: temporarily sending this command in both ways for backwards compatibility
        $request = [
            'objectPath'   => "/Script/PortaInterface.Default__PortaSequenceSubsystem",
            'functionName' => "PlaySequencesStatic",
            'parameters'   => [
                'Sequences' => $sequences,
            ],
        ];

        // this.MakeRequest(channel, "/remote/object/call", "PUT", request, batch);
        $batch = $this->addToBatch("/remote/object/call", "PUT", $request, $batch);

        // return this.SendRequest(channel, "/sequence/play", "PUT", { Sequences: sequences });
        // we will be sending all at once, so add to batch instead of sending alone
        return $this->addToBatch("/sequence/play", "PUT", [
            'Sequences' => $sequences,
        ], $batch);
    }

    /**
     * UnrealEngine.js > UEEvents > GetFunctionArgsFromSubmission(key, submission)
     */
    protected function getFunctionArgsFromSubmission($key, $submission) : array
    {
        if (isset($submission[$key])) {
            return $submission[$key][self::UEFuncArgSuffix] ?? [];
        }

        return [];
    }

    /**
     * UnrealEngine.js > UEEvents > RunFunction(channel, presetName, funcName, params, batch)
     */
    protected function runFunction($channel, $presetName, $funcName, $params, $batch) : EngineBatch
    {
        $payload = ['Parameters' => $params];
        $url = "/remote/preset/" . $presetName . "/function/" . $funcName;
        return $this->addToBatch($url, "PUT", $payload, $batch);
    }

    /**
     * @todo - needs batching or sending? return value seems like it won't work
     *
     * UnrealEngine.js > UEEvents > async GetLevelSequences(channel)
     */
    public function getLevelSequences($channel)
    {
        // const sequences = await this.SendRequest
        $sequences = $this->prepareRequest(
            $channel,
            "/remote/search/assets",
            "PUT",
            //    {Filter: {ClassNames: ["LevelSequence"]}}
            [
                "Filter" => [
                    "ClassNames" => ["LevelSequence"],
                ],
            ],
        );

        // return sequences?.Assets || [];
        return $sequences['Assets'] ?? [];
    }

    /**
     * @todo - needs batching or sending? return value seems like it won't work
     *
     * UnrealEngine.js > UEEvents > async getAssetsOfType(channel, type, folder)
     */
    public function getAssetsOfType($channel, $type, $folder)
    {
        $payload = [
            "Filter" => [
                "ClassNames"       => [$type],
                "RecursiveClasses" => true,
            ],
        ];
        if (isset($folder) && !is_array($folder) && !is_object($folder)) {
            $payload["Filter"]["PackagePaths"] = [$folder];
            $payload["Filter"]["RecursivePaths"] = true;
        }
        // const assets = await this.SendRequest(channel, "/remote/search/assets", "PUT", payload);
        $assets = $this->prepareRequest($channel, "/remote/search/assets", "PUT", $payload);

        // return assets?.Assets || [];
        return $assets['Assets'] ?? [];
    }

    /**
     * @todo - needs batching or sending? return value seems like it won't work
     *
     * UnrealEngine.js > UEEvents > async GetStreamingLevels(channel)
     */
    public function getStreamingLevels($channel)
    {
        $levels = $this->prepareRequest($channel, "/remote/object/call", "PUT", [
            "objectPath"   => "/Script/PortaInterface.Default__PortaLevelSubsystem",
            "functionName" => "GetStreamingLevelsStatic",
        ]);
        //     return levels?.Levels || [];
        return $levels['Levels'] ?? [];
    }

    /**
     * UnrealEngine.js > UEEvents > LoadStreamingLevel(channel, level, batch)
     */
    public function loadStreamingLevel($channel, $level, $batch) : EngineBatch
    {
        //     return this.MakeRequest(channel, "/remote/object/call", "PUT", {
        //             objectPath: "/Script/PortaInterface.Default__PortaLevelSubsystem",
        //             functionName: "LoadStreamingLevelStatic",
        //             parameters: {
        //             Name: level,
        //             UnloadCurrent: true
        //         },
        //         async: true
        //     }, batch);
        return $this->addToBatch("/remote/object/call", "PUT", [
            "objectPath"   => "/Script/PortaInterface.Default__PortaLevelSubsystem",
            "functionName" => "LoadStreamingLevelStatic",
            "parameters" => [
                "Name" => $level,
                "UnloadCurrent" => true,
            ],
            "async" => true,
        ], $batch);
    }

    /**
     * UnrealEngine.js > UEEvents > setTextureAsset(channel, preset, prop, image)
     */
    private function setTextureAsset($channel, $preset, $prop, $image) : array
    {
        $request = [
            'objectPath'   => "/Script/PortaInterface.Default__PortaAssetSubsystem",
            'functionName' => "SetTextureAssetFromPresetStatic",
            'parameters'   => [
                "PresetName" => $preset,
                "PropertyName" => $prop,
                "Texture" => $image
            ],
        ];

        //     return this.SendRequest(channel, "/remote/object/call", "PUT", request);
        return $this->prepareRequest($channel, "/remote/object/call", "PUT", $request);
    }

    /**
     * UnrealEngine.js > UEEvents > handleCustomEvent(e, channel, preset, data, schema)
     */
    public function handleCustomEvent($e, $channel, $preset, $data, $schema, EngineBatch $batch)
    {
        if (isset($e['type']) && $e['type'] === "uefunc") {
            $this->runFunction(
                $channel,
                $preset,
                $e['component']['key'],
                $this->getFunctionArgsFromSubmission($e['component']['key'], $data),
                $batch
            );
        } elseif (isset($e['type']) && $e['type'] === "uesend") {
            $submit_group = [];
            if (isset($e['component']['submitgroup'])) {
                // submitgroup = e.component.submitgroup.map((x) => x.submitgroup);
                $submit_group = array_map(function($component){
                    return $component['submitgroup'];
                }, $e['component']['submitgroup']);
            }
            // this.sendSubmission(channel, preset, data, schema, submitgroup);
            // @jess this may need to move?
            $this->buildSubmission($channel, $data, $schema, $preset, $submit_group);

        } elseif(isset($e['type']) && str_starts_with($e['type'], "uesend_channel_")) {
            // const ch = data[
            //      "__ChannelSelect_" + e.type.slice("uesend_channel_".length)
            // ]?.value || channel;
            $ch = $data["__ChannelSelect_" . substr($e['type'], 0, strlen("uesend_channel_"))];

            $submit_group = [];
            if (isset($e['component']['submitgroup'])) {
                // submitgroup = e.component.submitgroup.map((x) => x.submitgroup);
                $submit_group = array_map(function($component){
                    return $component['submitgroup'];
                }, $e['component']['submitgroup']);
            }

            // this.sendSubmission(ch, preset, data, schema, submitgroup);
            // @jess this may need to move?
            $this->buildSubmission($ch, $data, $schema, $preset, $submit_group);
        }
    }

    /**
     * split from UnrealEngine.js > UEEvents > sendPropertyValues(channel, presetName, props, batch)
     */
    public function preparePropertyValues($preset_name, $props, $batch)
    {
        foreach ($props as $prop_name => $prop) {
            $batch = $this->preparePropertyValue($preset_name, $prop_name, $prop, $batch);
        }
        return $batch;
    }

    /**
     * Add request to the batch without sending,
     *
     * Has standalone calls, unlike UEEvents.sendPropertyValues
     *
     * @param $presetName
     * @param $propName
     * @param $value
     * @param $batch
     *
     * @return EngineBatch
     */
    public function preparePropertyValue($presetName, $propName, $value, $batch) : EngineBatch
    {
        return $this->addToBatch(
            "/remote/preset/" . $presetName . "/property/" . $propName,
            "PUT",
            ["PropertyValue" => $value],
            $batch
        );
    }

    /**
     * Add request to batch's collection, don't send yet
     *
     * split from UnrealEngine.js > UEEvents > MakeRequest()
     *
     * @param string               $url
     * @param string               $verb
     * @param string|array         $content
     * @param EngineBatch $batch
     *
     * @return EngineBatch
     */
    protected function addToBatch(string $url, string $verb, string|array $content, EngineBatch $batch) : EngineBatch
    {
        /** @var UnrealBatch $batch */
        $batch->addRequest($url, $verb, $content);
        return $batch;
    }

    /**
     * UnrealEngine.js > UEEvents > checkSubmitGroup(schema, group, key)
     */
    protected function checkSubmitGroup($schema, $group, $key) : bool
    {
        Log::warning("Unreal's `checkSubmitGroup` is not yet implemented");
        // ray("checkSubmitGroup params: ", "schema: ", $schema, "group: ", $group, "key:", $key)->green();
        return true;

        // todo: implement. -- how to fake/convert Utils.getComponent ??

        // if (!group || group.length === 0 || (group.length === 1 && !group[0])) {
        if (!isset($group) || !$group || ($this->checkGroupSize($group))) {
            return true;
        }
        //
        // const comp = Utils.getComponent(schema.components, key);
        $comp = $this->schemaHelper->getComponent($schema['components'], $key);
        //
        // if (comp) {
        if ($comp) {
        //     if (comp.submitgroup) {
            if ($comp['submitgroup']) {
    //         if (
    //             comp.submitgroup.find((el) =>
    //                 group.includes(el.submitgroup)
    //             )
    //         ) {
    //             return true;
    //         }
                /*if (
                    $comp['submitgroup'].find((el) =>
                        $group.includes(el.submitgroup)
                    )
                ) {
                    return true;
                }*/
            }
        }
        return false;
    }

    /**
     * JS treats strings like arrays in ways PHP may not
     */
    private function checkGroupSize($group) : bool
    {
        // if (group.length === 0 || (group.length === 1 && !group[0]))
        return (
            (is_string($group) && strlen($group) === 0)
            ||
            (is_countable($group) && sizeof($group) === 0)
        )
        || (
            (
                (is_string($group) && strlen($group) === 1)
                ||
                (is_countable($group) && sizeof($group) === 1)
            )
            // from JavaScript --> && !group[0]
            && empty($group[0])
        );
    }

    /**
     * UnrealEngine.js > UEEvents > checkIpsumUEProperties(data, schema)
     */
    public function checkIpsumUEProperties($data, $schema)
    {
        Log::warning("Unreal's `checkIpsumUEProperties` is not yet implemented");
        return $data;
        /*const sportsSoccerProperties = SportsSoccer.getSportsSoccerProperties();
        const weatherProperties = Weather.getWeatherProperties();
        const t = this;

        Object.keys(data).forEach(function(key) {
            let result = [];
            let result2 = [];
            result = weatherProperties.filter(obj => {
                //return obj.value === key
                return key.match(obj.value);
            })
            if (!result.length) {
                result2 = sportsSoccerProperties.filter(obj => {
                    //return obj.value === key
                    return key.match(obj.value);
                })
            }
            if (result.length || result2.length) { //is ipsum component
                let component = FormioHelper.findNodeWithKey(schema, key);
                if (!component)
                    component = Utils.getComponent(schema.components, key);
                if (component && component.ueprop && typeof component.ueprop === 'string' && component.ueprop !== key) {
                    if (component.ueprop.match('.')) { //it's an object array
                        let ueprop_arr = component.ueprop.split('.');
                        let ueprop_last = ueprop_arr.length - 1;
                        let temp_ueprop = {};
                        temp_ueprop[ueprop_arr[ueprop_last]] = String(data[key]);
                        for (let j = ueprop_last - 1; j >= 0; j--) {
                            let temp_ueprop2 = temp_ueprop;
                            temp_ueprop = {};
                            temp_ueprop[ueprop_arr[j]] = temp_ueprop2;
                        }
                        data = t.mergeDeep(data, temp_ueprop);
                    }
                    else {
                        data[component.ueprop] = String(data[key]);
                    }
                    delete data[key];
                }
            }
        });
        return data;*/
    }

    // --------------------------------------------------------------------
    // SO FAR UNUSED IN JS:
    // --------------------------------------------------------------------
    // UnrealEngine.js > getSequenceComponent(data)

}
