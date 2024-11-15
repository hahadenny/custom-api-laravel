<?php

namespace App\Services\Engines\D3;

use App\Services\Engines\EngineEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NOTE: can likely use App\Services\D3Service instead
 */
class D3Event extends EngineEvent
{
    protected D3Batch $batch;

    public const MAIN_WALL_ADDRESS_PATTERN = 'main wall';
    public const OVERLAY_FILENAME_PATTERN = 'overlay';
    public const VIDEO_FIELD_NAME = 'video';
    public const VIDEO_FIELD_TYPE = 'resource';

    // For making direct HTTP requests to Porta Bridge
    protected string $BRIDGE_HOST;
    protected int|string $BRIDGE_PORT;
    protected const BRIDGE_HTTP_ENDPOINT = 'GetD3Api';

    public function __construct(
        protected string $namespace = 'disguise',
    )
    {
        parent::__construct(namespace: $this->namespace);

        // on-prem Porta Bridge is running on the same machine as Porta API
        $this->BRIDGE_HOST = config('services.bridge.host');
        $this->BRIDGE_PORT = config('services.bridge.port');

        // Log::debug("construct D3Event for $this->BRIDGE_HOST:$this->BRIDGE_PORT");
    }

    /**
     * @param $channel
     * @param $data
     * @param $schema
     * @param ...$params - use_axios=false
     *
     * @return null|array
     * @throws \HttpException
     */
    public function buildSubmission($channel, $data, $schema, ...$params)
    {
        // Log::debug("building submission for channel: $channel with data: ".json_encode($data));

        // in case $use_axios was not sent, make sure we avoid undefined errors when destructuring
        [$use_axios] = $params + [null, null, null];

        if ( !$data) {
            return null;
        }

        if ($this->schemaHelper->findNodesBeginningWith($schema, 'transport_')) {
            $this->prepareTransportControl($channel, $data, $schema);
        }
        if ($this->schemaHelper->findNodesBeginningWith($schema, 'indirection_')) {
            $this->prepareIndirection($channel, $data, $schema, $use_axios);
        }
        if ($this->schemaHelper->findNodesBeginningWith($schema, 'patch')) {
            $this->preparePatchData($channel, $data, $schema);
        }

        return isset($this->batch) ? $this->batch->prepareToSend($channel) : null;
    }

    protected function prepareTransportControl($channel, $data, $schema)
    {
        // Log::debug("prepareTransportControl -- for channel: $channel with data: ".json_encode($data));
        // ray($data)->label('transport data')->green();

        // Bridge is expecting url to be an array,
        // so hack it to send body in urls array for now
        $urlData = [];

        $dataBySuffix = $this->schemaHelper->groupRelatedSubmissionData($data);

        // /transport/engaged
        // keep track of the transports to engage
        $engageBody = [
            "transports" => []
        ];

        // /transport/stop
        // keep track of the transports to stop
        $stopBody = [
            "transports" => []
        ];

        // /transport/gototrack
        // keep track of tracks to change
        $goToTrackBody = [
            "transports" => []
        ];

        $goToTagBody = [
            "transports" => []
        ];

        $goToTimeBody = [
            "transports" => []
        ];

        $goToTimeCodeBody = [
            "transports" => []
        ];

        // play, stop, playsection, playloopsection, none
        $actionTransports = [
            /*play: [] // array of transports */
        ];

        // (? 1. feed outputs (for screens))
        // if ($data['feed_outputs'] && $data['feed_outputs'] !== 'default') {
        //     $urls []= ["/transportcontrol/" . $data['feed_outputs']];
        // }

        // 2. for each track
        foreach ($dataBySuffix as $i => $data) {

            // ray($data)->label('transport data By Suffix data')->green();

            if (!is_numeric($i) || !isset($data['transport'])) {
                // this isn't transport field data
                continue;
            }

            // 3. engage transport
            $transport = $data['transport'];

            $engageBody['transports'][]= [
                'transport' => ['uid' => $transport ],
                'engaged' => true
            ];

            // 3a. stop the track first if jump is required
            if (isset($data['jump_to']) && $data['jump_to'] !== 'default' && $data['action'] !== 'none') {
                $stopBody['transports'] []= ['uid'=>$transport];
            }

            // 4. select track
            if (isset($data['track']) && !isset($data['jump_to_tag'])) {
                $goToTrackBody['transports'][]=[
                    'transport' => [
                        'uid' => $transport,
                    ],
                    'track' => [
                        'uid' => $data['track'],
                    ],
                    'playmode' => "NotSet"
                ];
            }

            /*// 5. set brightness and volume
            if (isset($data['brightness'])) {
                $urls []= ["/transportcontrol/transports/" . $transport . "/brightness/" . $data['brightness']];
            }
            if (isset($data['volume'])) {
                $urls []= ["/transportcontrol/transports/" . $transport . "/volume/" . $data['volume']];
            }*/

            // 6. jump to
            if(isset($data['jump_to'])){
                if ( in_array($data['jump_to'], ['TC', 'MIDI', 'CUE']) && isset($data['jump_to_tag']) ) {
                    $goToTagBody['transports'][]= [
                        'transport' => [
                            'uid' => $transport,
                        ],
                        'type' => $data['jump_to'],
                        'value' => (string)$data['jump_to_tag'],
                        'allowGlobalJump' => true,
                        'playmode' => "NotSet"
                    ];
                } elseif ($data['jump_to'] === 'time') {
                    $goToTimeBody['transports'][]= [
                        'transport' => [
                            'uid' => $transport,
                        ],
                        'time' => floatval($data['jump_to_time']),
                        'playmode' => "NotSet"
                    ];
                } elseif ($data['jump_to'] !== 'default') {
                    // not a tag or time, i.e. next/prev section
                    // urls.push(["/transportcontrol/transports/"+transport+"/"+data['jump_to']]);
                    if(!isset($goToTransports[$data['jump_to']])){
                        $goToTransports[$data['jump_to']] = [];
                    }
                    $goToTransports[$data['jump_to']][]= [
                        'transport' => [
                            'uid' => 'transport',
                        ],
                        'playmode' => "NotSet" // taken care of by `actionTransports`
                    ];
                }
            }

            // 7. action
            if (isset($data['action']) && $data['action'] !== 'none') {
                if(!isset($actionTransports[$data['action']])){
                    $actionTransports[$data['action']] = [];
                }
                $actionTransports[$data['action']][]= [
                    'transport' => [
                        'uid' => $transport,
                    ],
                ];
            }

        } // end for

        // ray($engageBody)->label('bodies -- $engageBody')->blue();
        // ray($stopBody)->label('bodies -- $stopBody')->blue();
        // ray($goToTrackBody)->label('bodies -- $goToTrackBody')->blue();
        // ray($goToTimeBody)->label('bodies -- $goToTimeBody')->blue();
        // ray($goToTagBody)->label('bodies -- $goToTagBody')->blue();
        // ray($actionTransports)->label('bodies -- $actionTransports')->blue();

        if (sizeof($engageBody['transports']) === 0) {
            Log::info("No transports to engage");
            return;
        }

        $urlData[]=['/api/session/transport/engaged', json_encode($engageBody, JSON_UNESCAPED_SLASHES)];

        if (sizeof($stopBody['transports']) > 0) {
            $urlData[]=['/api/session/transport/stop', json_encode($stopBody, JSON_UNESCAPED_SLASHES)];
        }
        if (sizeof($goToTrackBody['transports']) > 0) {
            $urlData[]=['/api/session/transport/gototrack', json_encode($goToTrackBody, JSON_UNESCAPED_SLASHES)];
        }
        if (sizeof($goToTimeBody['transports']) > 0) {
            $urlData[]=['/api/session/transport/gototime', json_encode($goToTimeBody, JSON_UNESCAPED_SLASHES)];
        }
        if (sizeof($goToTagBody['transports']) > 0) {
            $urlData[]=['/api/session/transport/gototag', json_encode($goToTagBody, JSON_UNESCAPED_SLASHES)];
        }
        if(sizeof($actionTransports) > 0){
            foreach ($actionTransports as $action => $transports){
                $urlData[]=[
                    '/api/session/transport/' . $action,
                    json_encode(['transports' => $transports], JSON_UNESCAPED_SLASHES)
                ];
            }
        }

        // ray($urlData)->label('transport urlData')->green();
        // Log::debug('============================');
        Log::debug('transport urlData: ');
        Log::debug($urlData);
        Log::debug('============================');

        $this->batch ??= new D3Batch($this);
        $this->batch->addRequest($urlData, "POST", null);
    }


    // TODO: NEEDS UPDATING
    protected function prepareIndirection($channel, $data, $schema, $use_axios)
    {
        $url = "/indirections/set";
        $assignments = [];
        for ($i = 0; $i < $data['indirection_no']; $i++) {
            // $uid = data['indirection_'+i].split('-')[1];
            $uid = explode('-', $data['indirection_' . $i])[1];

            if (isset($data['resource_from_'.$i])) {
                if ($data['resource_from_'.$i] === 'porta' || $data['resource_from_'.$i] === 'share') {
                    if (isset($data['media_id_'.$i])) {
                        if (!is_numeric($data['media_id_'.$i])) { //not numeric with error message
                            $file = '';
                            if (isset($data['share_file_'.$i])){
                                $file = $data['share_file_'.$i];
                            }
                            else{
                                $file = $data['d3_media_'.$i]['name'];
                            }
                            Log::error($file . ' Upload Error: ' . $data['media_id_'.$i], ['uid'=>$uid]);
                        }
                        else {
                            $adata = ['uid' => $uid, 'resourceUid'=> $data['media_id_'.$i]];
                            $assignments []= $adata;
                        }
                    }
                    else {
                        $adata = ['uid' => $uid, 'resourceUid'=> 0];
                        $assignments []= $adata;
                    }
                }
                elseif ($data['resource_from_'.$i] === 'local') {
                    if (isset($data['resource_'.$i])) {
                        $adata = ['uid' => $uid, 'resourceUid'=> $data['resource_'.$i]];
                        $assignments []= $adata;
                    }
                    else {
                        $adata = ['uid' => $uid, 'resourceUid'=> 0];
                        $assignments []= $adata;
                    }
                }
            }
            elseif (isset($data['resource_'.$i])) {
                $adata = ['uid' => $uid, 'resourceUid'=> $data['resource_'.$i]];
                $assignments []= $adata;
            }
            else {
                $adata = ['uid' => $uid, 'resourceUid'=> 0];
                $assignments []= $adata;
            }
        }

        $pdata = ['assignments' => $assignments];
        $pdata = json_encode($pdata, JSON_UNESCAPED_SLASHES);

        $urls = [[$url, $pdata]];

        $this->batch ??= new D3Batch($this);
        $this->batch->addRequest($urls, "POST", null);
    }

    protected function determineFieldValueName($field) : string
    {
        $valueType = $field['type'];
        if(in_array($valueType, ['notch', 'easingFunction'])){
            // type is not used as the value name for notch or easingFunc layer
            $valueType = 'float';
        }

        if($valueType === "notchLayer"){
            $valueType = "string";
        }

        if(!in_array($valueType, ['notch', 'easingFunction', 'float', 'string', 'resource'])){
            Log::warn("D3 patch $field '{$field['name']}' (address: '{$field['address']}') contains an unusual type: '{$field['type']}'");
        }

        return $valueType . 'Value';
    }

    protected function fieldIsRelatedToNotch($field) : bool
    {
        return isset($field['notchAddress']);
    }

    protected function isNotchField($field) : bool
    {
        // notch field name always contains "Value::Attributes"
        return str_contains($field['name'], "Value::Attributes");
    }

    protected function isDurationField($field) : bool
    {
        return $field['name'] === "duration";
    }

    protected function isEasingFuncField($field) : bool
    {
        return $field['name'] === "easingFunction";
    }

    protected function isNotchLayerField($field) : bool
    {
        return $field['name'] === "layerlayerlayer";
    }

    /**
     * @param {float} valueA
     * @param {float} valueB
     * @returns {float[]}
     */
    protected function swapValues($valueA, $valueB) : array
    {
        if(!$valueA && $valueA !== 0){
            $valueA = 0;
        }
        if(!$valueB && $valueB !== 0){
            $valueB = 0.5;
        }
        if ($valueA !== $valueB) {
            [$valueA, $valueB] = [$valueB, $valueA];
        } else{
            // make sure they're different
            $valueB = $valueA === 0 ? 0.5 : 0;
        }
        return [$valueA, $valueB];
    }

    /**
     * Determine the video layer address that we will target for any media file changes.
     * This is the layer that corresponds with the transition `value`; Layer A for 0, B for 0.5
     *
     * @param {object} allFieldChanges
     * @param {array} notchVideoFields
     */
    protected function determineNotchVideos($allFieldChanges, $notchVideoFields, $currentPatches)
    {
        // don't need to clone because it's not a constant
        $fieldChanges = $allFieldChanges;
        // find notch field changes
        foreach($fieldChanges as $address => $changes) {
            // arrays are not changed by ref so we don't need to check $fieldChanges[$address] like in JS
            $notchChanges = array_filter($changes, function($change) {
                return $this->isNotchField(['name' => $change['field']]);
            });
            // there should only be one match
            $notchChange = array_shift($notchChanges);
            if(isset($notchChange)) {
                $notchTargetValue = $notchChange['floatValue']['value'];
                $fieldChanges = $this->determineNotchVideo($address, $notchTargetValue, $fieldChanges, $notchVideoFields, $currentPatches);
            }
        }

        return $fieldChanges;
    }

    protected function determineNotchVideo($changeAddress, $notchTargetValue, $fieldChanges, $notchVideoFields, $currentPatches) {

        $changes = $fieldChanges;

        /**
         * [
         * "Frame 1" => [
                    0 => [...],
                    1 => [...],
                    "notchPatchAddress" => "Frame 1 Notch"
                ]
         * ]
         */
        // find video fields
        $videoFields = Arr::first($notchVideoFields, function($vidFields) use ($changeAddress) {
            return isset($vidFields) && $vidFields['notchPatchAddress'] === $changeAddress;
        });

        if(empty($videoFields)){
            return $changes;
        }
        // make it just an array of the 2 fields
        unset($videoFields['notchPatchAddress']);

        // find video field for layer A
        $videoFieldA = Arr::first($videoFields, function ($videoField){
            return Str::endsWith(Str::upper(trim($videoField['address'])), "A");
        });
        // find video field for layer B
        $videoFieldB = Arr::first($videoFields, function ($videoField){
            return Str::endsWith(Str::upper(trim($videoField['address'])), "B");
        });

        if(empty($videoFieldA) && empty($videoFieldB)){
            return $changes;
        }

        // find the video field we should be changing
        $formVideoFieldKey = Arr::first(array_keys($changes),
            function($videoFieldKey) use ($videoFieldA, $videoFieldB) {
                return in_array(
                    Str::upper(trim($videoFieldKey)),
                    [
                        Str::upper(trim($videoFieldA['address'])),
                        Str::upper(trim($videoFieldB['address']))
                    ]
                );
            }
        );
        $formVideoField = $changes[$formVideoFieldKey];

        // find min/max values for the notch layer
        $notchField = null;
        $notchPatch = Arr::first($currentPatches, function($currentPatch) use ($changeAddress, &$notchField){
            $fieldMatch = Arr::first($currentPatch['fields'], function($field){
                return $this->isNotchField($field);
            });
            $notchField = $fieldMatch ?? null;
            return isset($fieldMatch) && $currentPatch['address'] === $changeAddress;
        });
        $notchMin = $notchField['floatMeta']['min']; // A
        $notchMax = $notchField['floatMeta']['max']; // B


        // need floatMeta.min/max to know which layer to target
        if($notchTargetValue === $notchMin){
            $targetVid = $videoFieldA;
            $otherVid = $videoFieldB;
        } elseif($notchTargetValue === $notchMax){
            $targetVid = $videoFieldB;
            $otherVid = $videoFieldA;
        } else {
            // couldn't find the notch target value to set, use the closest one
            Log::warning(`Notch target value "${notchTargetValue}" is not a valid value for notch layer`);
            $closestValue = $this->closestToFloat($notchMin, $notchMax, $notchTargetValue);
            if($closestValue === $notchMin){
                $targetVid = $videoFieldA;
                $otherVid = $videoFieldB;
            } else {
                $targetVid = $videoFieldB;
                $otherVid = $videoFieldA;
            }
        }

        // set this as the target address
        $changes[$targetVid['address']] = array_values($formVideoField);
        // remove data for the other vid
        unset($changes[$otherVid['address']]);

        return $changes;
    }

    protected function closestToFloat($valueA, $valueB, $targetFloat) {
        $diffA = abs($valueA - $targetFloat);
        $diffB = abs($valueB - $targetFloat);

        if ($diffA < $diffB) {
        return $valueA;
        } else if ($diffB < $diffA) {
            return $valueB;
        } else {
            // If differences are equal, return valueA by default
            return $valueA;
        }
    }

    /**
     * Make sure the Main Wall layer flips to the overlay image if we send a Frame Layer.
     *
     * Send a Main Wall Notch and a Main Wall Video change that includes the correct
     * target layer and overlay file to switch to. The target Main Wall Video Layer
     * will be calculated based on the existing Main Wall state
     *
     * @param        $channel
     * @param array  $allFieldChanges
     * @param array  $currentPatches
     * @param int    $fieldDuration
     * @param string $fieldEasingFunction
     *
     * @return array
     * @throws \Exception
     */
    protected function checkMainWallState($channel, array $allFieldChanges, array $currentPatches, int $fieldDuration, string $fieldEasingFunction) : array
    {
        $fieldChanges = $allFieldChanges;


        // Determine whether we should transition the Main Wall (full width) layer to the overlay image.
        // We should do this if:
        //      - the current batch of changes has a video field (see `frameVideosSent` in sendPatchesData())
        //      - the main wall is not present in the changes being sent
        //      - this video field is not for a Main Wall Layer
        //      - the known Main Wall state's video file is not the overlay image

        if($this->mainWallIsChanging($fieldChanges)){
            // the set of $fieldChanges already includes changing the Main Wall
            return $fieldChanges;
        }

        ['mainWallPatches' => $mainWallPatches, 'notchLayer' => $notchLayer] = $this->parsePatchesForMainWall($currentPatches);

        if(sizeof($mainWallPatches) <= 0){
            // nothing to flip
            return $fieldChanges;
        }

        $overlayLayer = $this->parseMainWallLayersForOverlay($mainWallPatches, $notchLayer);

        if(isset($overlayLayer) && $this->isNotchVideoLayerVisible($notchLayer, $overlayLayer)) {
            // the overlay is already visible; we don't need to change the main wall layer
            return $fieldChanges;
        }

        // update the field changes data to also flip the main wall layer to the overlay image
        $mainWallNotchField = $this->findNotchFieldInLayer($notchLayer);
        $mainWallNotchCurrentValue = $mainWallNotchField['floatValue']['currentValue'];
        $minValue = $mainWallNotchField['floatMeta']['min'];
        $maxValue = $mainWallNotchField['floatMeta']['max'];

        // set up the main wall video layer to use
        $targetLayer = $this->findTargetNotchVideoLayer($mainWallPatches, $notchLayer);

        // determine the overlay file to use
        if(!isset($overlayLayer)){
            // overlay isn't currently used in a patch value so find the overlay file in local files
            $resourcesData = $this->getResourcesByType($channel);
                $videoResources = $resourcesData['resources']['VideoClip'];
                $overlayResource = Arr::first($videoResources, function ($resource) {
                    return Str::contains(Str::lower($resource['name']), 'overlay');
                });
            $overlayFileUid = $overlayResource['uid'] ?? null;
        } else {
            $overlayFileField = Arr::first($overlayLayer['fields'], function ($field) {
                return $field['type'] === self::VIDEO_FIELD_TYPE && $field['name'] === self::VIDEO_FIELD_NAME;
            });
            $overlayFileUid = $overlayFileField['resourceValue']['uid'];
        }

        // add the main wall changes to the existing changes we want to send
        return [...$fieldChanges, ...[
            $notchLayer['address'] => [
                $mainWallNotchField['name'] => [
                    'field' => $mainWallNotchField['name'],
                    'floatValue' => [
                        // from this value
                        'startValue' => $mainWallNotchCurrentValue,
                        // to this value
                        'value' => $mainWallNotchCurrentValue === $maxValue ? $minValue : $maxValue,
                        // make sure easing and duration is the same as another field change is using
                        // so it's not jarring
                        'easingFunction' => $fieldEasingFunction,
                        'duration' => $fieldDuration
                    ]
                ]
            ],
            $targetLayer['address'] => [
                // video field name is 'video', and the same for all layers
                self::VIDEO_FIELD_NAME => [
                    'field' => self::VIDEO_FIELD_NAME,
                    'resourceValue' => [
                        'uid' => $overlayFileUid
                    ]
                ]
            ]
        ]];
    }

    /**
     * @throws \Exception
     */
    protected function preparePatchData($channel, $data, $schema)
    {
        // ray($channel)->green()->label('sendPatchData -- channel');
        // ray($data)->label('sendPatchData -- data');
        // ray($schema)->label('sendPatchData -- schema');
        // Log::debug($channel);
        // Log::debug($data);

        $url = "/api/experimental/sockpuppet/live";
        $patches = [];
        $allFieldChanges = [];
        $fieldValues = [];
        $notchVideoFields = null;
        /** @type {object|null} - normally set to the newly fetched notch field's `floatValue.currentValue` */
        $matchingField = null;
        // switch to check if we are sending any single frame (not full/main wall) videos
        $frameVideosSent = false;
        // save samples of another notch field's data to use for the main wall auto-transition (if it is needed)
        $fieldEasingFunction = null;
        $fieldDuration = null;

        // get up-to-date `currentValue` of all patches
        // Log::debug("before making bridge http for channel: $channel -- with data ".json_encode($data));

        $httpResponse = $this->makeBridgeHttpRequest("/api/experimental/sockpuppet/patches");

        // Log::debug("after making bridge http for channel: $channel -- with data ".json_encode($data));

        // TODO: REMOVE ---- FOR TESTING WHILE BRIDGE IS BROKEN -----
        /*$httpResponse = '{
          "channel":"HTTP",
          "sender":"G9HgCrCbJy1lWW7gAAAs",
          "message":{
            "sender":"G9HgCrCbJy1lWW7gAAAs",
            "url":[
              "/api/experimental/sockpuppet/patches"
            ],
            "verb":"GET",
            "message_id":"999",
            "response":[
              "{\"status\":{\"code\":0,\"message\":\"\",\"details\":[]},\"result\":[{\"address\":\"Main Wall Notch\",\"fields\":[{\"name\":\"Value::Attributes::8f03a21d-ff21-11ed-bad3-4851c5e6602b\",\"displayName\":\"Value\",\"type\":\"float\",\"floatMeta\":{\"min\":0,\"max\":1,\"defaultValue\":0,\"step\":0.01},\"floatValue\":{\"value\":0,\"duration\":0,\"easingFunction\":\"\",\"startValue\":0,\"currentValue\":0}}]},{\"address\":\"Main Wall Video A\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Main Wall Video B\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 1 Notch\",\"fields\":[{\"name\":\"Value::Attributes::8f03a21d-ff21-11ed-bad3-4851c5e6602b\",\"displayName\":\"Value\",\"type\":\"float\",\"floatMeta\":{\"min\":0,\"max\":1,\"defaultValue\":0,\"step\":0.01},\"floatValue\":{\"value\":0,\"duration\":0,\"easingFunction\":\"\",\"startValue\":0,\"currentValue\":0}}]},{\"address\":\"Frame 1 - A\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 1 - B\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 2 Notch\",\"fields\":[{\"name\":\"Value::Attributes::8f03a21d-ff21-11ed-bad3-4851c5e6602b\",\"displayName\":\"Value\",\"type\":\"float\",\"floatMeta\":{\"min\":0,\"max\":1,\"defaultValue\":0,\"step\":0.01},\"floatValue\":{\"value\":0,\"duration\":0,\"easingFunction\":\"\",\"startValue\":0,\"currentValue\":0}}]},{\"address\":\"Frame 2- A\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 2 -B\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 3 Notch\",\"fields\":[{\"name\":\"Value::Attributes::8f03a21d-ff21-11ed-bad3-4851c5e6602b\",\"displayName\":\"Value\",\"type\":\"float\",\"floatMeta\":{\"min\":0,\"max\":1,\"defaultValue\":0,\"step\":0.01},\"floatValue\":{\"value\":0,\"duration\":0,\"easingFunction\":\"\",\"startValue\":0,\"currentValue\":0}}]},{\"address\":\"Frame 3 - A\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 3 - B\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 4 Notch\",\"fields\":[{\"name\":\"Value::Attributes::8f03a21d-ff21-11ed-bad3-4851c5e6602b\",\"displayName\":\"Value\",\"type\":\"float\",\"floatMeta\":{\"min\":0,\"max\":1,\"defaultValue\":0,\"step\":0.01},\"floatValue\":{\"value\":0,\"duration\":0,\"easingFunction\":\"\",\"startValue\":0,\"currentValue\":0}}]},{\"address\":\"Frame 4 - A\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 4 - B\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 5 Notch\",\"fields\":[{\"name\":\"Value::Attributes::8f03a21d-ff21-11ed-bad3-4851c5e6602b\",\"displayName\":\"Value\",\"type\":\"float\",\"floatMeta\":{\"min\":0,\"max\":1,\"defaultValue\":0,\"step\":0.01},\"floatValue\":{\"value\":0,\"duration\":0,\"easingFunction\":\"\",\"startValue\":0,\"currentValue\":0}}]},{\"address\":\"Frame 5 - A\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]},{\"address\":\"Frame 5 - B\",\"fields\":[{\"name\":\"video\",\"displayName\":\"Video\",\"type\":\"resource\",\"resourceMeta\":{\"type\":\"VideoClip\"},\"resourceValue\":{\"uid\":\"0\",\"name\":\"\"}}]}]}"
            ]
          }
        }';*/
        // END FOR TESTING

        $currentPatches = $this->parseBridgeHttpResponse($httpResponse);
        // Log::debug("after parsing bridge http for channel: $channel -- with data ".json_encode($data));

        $patchData = array_filter($data, function($value, $key) use ($schema) {
            $componentSchema = $this->schemaHelper->findNodeWithKey($schema, $key);
            if(!str_contains($key, 'share_file_')
                && (!str_contains($key, 'resource_') || str_contains($key, 'resource_from_'))
                && (!str_contains($key, 'patch') || !isset($componentSchema['address']))
            ){
                // ray("Component '$key' is not a patch")->orange();
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        foreach ($patchData as $key => $value) {
            $componentSchema = $this->schemaHelper->findNodeWithKey($schema, $key);
            if(empty($componentSchema)){
                Log::warning(`No schema for Component "$key"`);
                // ray("No schema for Component with key: '$key'")->orange();
                continue;
            }

            if(!isset($value)){
                Log::warning(`Component "$key" has no value`);
                continue;
            }

            $patch = [
                'address' => $componentSchema['address'],
                'key' => $key,
                'changes' => []
            ];

            if(isset($componentSchema['notchAddress'])){
                $patch['notchAddress'] = $componentSchema['notchAddress'];
            }

            // build change entry for field
            $componentField = $componentSchema['field'];
            $fieldName = $componentField['originalField']['name'] ?? $componentField['name'];
            $fieldValueName = $this->determineFieldValueName($componentField);

            // values like notch's $floatValue may be an object that we need to append to,
            // so keep track of the value entries as one combined `$floatValue` obj
            // only track for fields of the same name
            if(!isset($fieldValues[$patch['address']])){
                $fieldValues[$patch['address']] = [];
            }
            if(!isset($fieldValues[$patch['address']][$fieldName])){
                $fieldValues[$patch['address']][$fieldName] = [];
            }
            if(!isset($fieldValues[$patch['address']][$fieldName][$fieldValueName])){
                $fieldValues[$patch['address']][$fieldName][$fieldValueName] = [];
            }

            // determine and set the correct `$fieldValues[$fieldValueName]` prop name(s) and value
            // $fieldValues = $this->>determineFieldValue(componentField, key, value, $fieldValueName, $fieldValues);
            if($componentField['type'] === 'string') {
                $fieldValues[$patch['address']][$fieldName][$fieldValueName] = $value;
            } elseif($componentField['type'] === 'resource') {
                if($this->fieldIsRelatedToNotch($componentField)){

                    if(Str::lower($componentField['notchAddress']) !== Str::lower(static::MAIN_WALL_ADDRESS_PATTERN)){
                        // the video is not for the "Main Wall"/full width layer
                        $frameVideosSent = true;
                    }

                    if(!isset($componentField['videoFields'])){
                        // this is the alt video field, ignore it
                        // we'll add the additional data later
                        continue;
                    }

                    if(isset($notchVideoFields[$patch['notchAddress']])){
                        // we already encountered the notch field
                        $notchVideoFields[$patch['notchAddress']] = [
                            ...$componentField['videoFields'],
                            ...$notchVideoFields[$patch['notchAddress']]
                        ];
                    } else {
                        // we haven't encountered the notch field yet
                        $notchVideoFields[$patch['notchAddress']] = $componentField['videoFields'];
                    }
                }

                // some resource fields have an object as the value
                if (is_string($value)) {
                    // the prop is `uid` if local, `name` if share file
                    if(Str::startsWith($key, 'share_file')){
                        $filename=$value;
                        if(Str::contains($value, '/')){
                            $parts = explode('/', $value);
                            $filename = trim($parts[sizeof($parts) - 1]);
                        } elseif(Str::contains($value, '\\')){
                            $parts = explode('\\', $value);
                            $filename = trim($parts[sizeof($parts) - 1]);
                        }
                        $fieldValues[$patch['address']][$fieldName][$fieldValueName] = [...$fieldValues[$patch['address']][$fieldName][$fieldValueName], 'name' => $filename];
                    } else {
                        $fieldValues[$patch['address']][$fieldName][$fieldValueName] = [...$fieldValues[$patch['address']][$fieldName][$fieldValueName], 'uid' => $value];
                    }
                } else {
                    // the value is an object
                    // porta file will have `url`, local&shared file will have `name`
                    if(!isset($value['source']) || $value['source'] === 'porta'){
                        // porta media often has no source
                        $parts = explode('/', $value['url']);
                        $filename = trim($parts[sizeof($parts) - 1]);
                    } else {
                        // TODO !!NOTE!! if getResourcesByType is used, it will have to format the same way for this to work
                        $parts = explode(' (', $value['name']);
                        $filename = trim($parts[0]);
                    }

                    $fieldValues[$patch['address']][$fieldName][$fieldValueName]['name'] = $filename;
                }
            } elseif($this->isNotchField($componentField)) {

                // find patches that match the addresses & names of the patches we're sending
                $matchingField = $this->findFieldOfPatchInPatches($currentPatches, $fieldName, $patch, $fieldValueName);

                // determine which layer to target for transition based on the currentValue and the min/max values
                $floatValues = $this->determineLayerValues($matchingField);
                $fieldValues[$patch['address']][$fieldName][$fieldValueName]['value'] = $floatValues['value'];
                $fieldValues[$patch['address']][$fieldName][$fieldValueName]['startValue'] = $floatValues['startValue'];

                // make sure we know which notch field belongs to which video so we can
                // set the transition correctly later
                if(isset($notchVideoFields[ $patch['notchAddress'] ])){
                    // we already encountered the videoFields
                    $notchVideoFields[$patch['notchAddress']]= [
                        ...$notchVideoFields[$patch['notchAddress']],
                        'notchPatchAddress' => $patch['address']
                    ];
                } else{
                    // we haven't encountered the related video fields yet
                    $notchVideoFields[$patch['notchAddress']] = [
                        'notchPatchAddress' => $patch['address']
                    ];
                }

            } elseif(($this->isDurationField($componentField) || $this->isEasingFuncField($componentField))
                && $this->fieldIsRelatedToNotch($componentField)){
                // ^^ any float can have easing and duration, so before making custom notch changes,
                //      check if it's related to a notch layer

                // combine duration & easingFunction into one $floatValue to include in the notch field's data
                if($this->isDurationField($componentField)){
                    $fieldValues[$patch['address']][$fieldName][$fieldValueName]['duration'] = $value;
                    // save for main wall auto transition if needed
                    $fieldDuration = $value;
                } elseif($this->isEasingFuncField($componentField)){
                    $fieldValues[$patch['address']][$fieldName][$fieldValueName]['easingFunction']  = $value;
                    // save for main wall auto transition if needed
                    $fieldEasingFunction = $value;
                }
            } elseif($this->isNotchLayerField($componentField)) {
                // notch layer is a `stringValue`, so just send as string
                $fieldValues[$patch['address']][$fieldName][$fieldValueName] = $value;
            } else {
                // the type is `floatValue`
                // floats now require all `floatValue` props present
                $fieldValues[$patch['address']][$fieldName][$fieldValueName]['value'] = $value;
                // we don't yet support transitions for all floats, so just use the current/defaults
                // setting startValue = value prevents easing animation
                $fieldValues[$patch['address']][$fieldName][$fieldValueName]['startValue'] = $value;
                // if duration is 0, D3 sets the value to NaN
                $fieldValues[$patch['address']][$fieldName][$fieldValueName]['duration'] = 1; // matchingField.floatValue.duration;
                $fieldValues[$patch['address']][$fieldName][$fieldValueName]['easingFunction'] = "Linear"; // matchingField.floatValue.easingFunction
            }

            $change = [
                'field' => $fieldName,
            ];
            $change[$fieldValueName] = $fieldValues[$patch['address']][$fieldName][$fieldValueName];
            // make sure field changes with the same name are combined
            if(!isset($allFieldChanges[$patch['address']])){
                $allFieldChanges[$patch['address']] = [];
            }
            if(!isset($allFieldChanges[$patch['address']][$fieldName])){
                $allFieldChanges[$patch['address']][$fieldName] = [];
            }

            $allFieldChanges[$patch['address']][$fieldName] = [...$allFieldChanges[$patch['address']][$fieldName], ...$change];
        } // end for data entries

        if($frameVideosSent) {
            // make sure frame videos won't be obscured by the full/main wall layer
            $allFieldChanges = $this->checkMainWallState($channel, $allFieldChanges, $currentPatches, $fieldDuration, $fieldEasingFunction);
        }

        if(isset($notchVideoFields)){
            $allFieldChanges = $this->determineNotchVideos($allFieldChanges, $notchVideoFields, $currentPatches);
        }

        // make sure patch with the same addresses are combined
        foreach($allFieldChanges as $address => $patch){
            $patchVals = array_values($patch);
            if(!isset($patches[$address])){
                $patches[$address] = [
                    'address' => $address,
                    'changes' => $patchVals
                ];
            } else {
                $patches[$address]['changes'] = array_merge($patches[$address]['changes'], $patchVals);
            }
        }

        Log::debug('patches sent: ');
        Log::debug($patches);
        // ray($patches)->label('sendPatchData -- patches');

        if(empty($patches)) {
            Log::warning(`NO PATCHES GIVEN`);
            // ray(`NO PATCHES GIVEN`, $data)->orange();
            return [];
        }

        $body = ['patches' => array_values($patches)];
        $body = json_encode($body, JSON_UNESCAPED_SLASHES);
        $urls = [[$url, $body]];

        $this->batch ??= new D3Batch($this);
        $this->batch->addRequest($urls, "POST", null);
    }

    protected function decodeIfJson(mixed $decodeMe) : array
    {
        return is_string($decodeMe) ? json_decode($decodeMe, true) : $decodeMe;
    }

    /**
     * @throws \Exception
     */
    protected function parseBridgeHttpResponse($httpResponse) : array
    {
        $httpDecodedResponse = $this->decodeIfJson($httpResponse);
        $httpResponseMessage = $this->decodeIfJson($httpDecodedResponse)['message'];
        $httpResponseMessageResponses = $this->decodeIfJson($httpResponseMessage)['response'];
        // assume one response since we only sent one URL with request
        $decodedResponse = $this->decodeIfJson($httpResponseMessageResponses[0]);

        if(isset($decodedResponse['status'])){
            return $this->parseD3ApiResponse($decodedResponse);
        }

        // v1 API does not have status prop to parse
        return $this->parseD3V1ApiResponse($decodedResponse);
    }

    /**
     * Parse a response from the D3 API that is not v1
     *
     * @param $response
     *
     * @return mixed
     * @throws \Exception
     */
    protected function parseD3ApiResponse($response) : array
    {
        $this->parseD3ResponseStatus($response);
        return $response['result'];
    }

    /**
     * Parse a response from the /v1/ D3 API
     *
     * @throws \Exception
     */
    protected function parseD3V1ApiResponse($response)
    {
        $result = $response['result'] ?? null;
        if(empty($result)){
            throw new \Exception('Http request to Bridge returned no results (v1 D3 API)');
        }
        return $result;
    }

    /**
     * Parse D3 API response's status (as passed through from bridge)
     * Note: /v1 D3 API endpoint does not return status
     *
     * @param $response
     *
     * @return string
     * @throws \Exception
     */
    protected function parseD3ResponseStatus($response) : string
    {
        $status = $response['status'] ?? null;
        if(!isset($status)){
            throw new \Exception('Http request to Bridge returned an error (no status)');
        }

        $statusCode = (int)($status['code'] ?? null);
        if(!isset($statusCode)){
            throw new \Exception('Http request to Bridge returned an error (no status code)');
        }

        $message = $status['message'] ?? '';

        /**
         * @see https://d3technologies.atlassian.net/wiki/spaces/RD/pages/1783267333/Status
         * 0: OK
         * 1000: Warning
         *  1001: Result is incomplete but correct
         * 2000: Logical error
         * 4000: Client error
         * 5000: Server error
         */
        if ($statusCode >= 1000 && $statusCode < 2000) {
            // warnings are ok, but note them
            $userMsg = 'Http request to Bridge returned a Warning: ' . $message . ' (status: ' . $statusCode . ')';
            Log::warn($userMsg);
        } elseif($statusCode !== 0 && $statusCode !== 200) {
            // sample error message: `(patches[1].changes[0].resourceValue.uid): invalid value "" for type TYPE_UINT64`
            $userMsg = 'Http request to Bridge returned an Error: ' . $message . ' (status: ' . $statusCode . ')';
            throw new \Exception($userMsg);
        } else {
            // status 0 or 200 means success
            $userMsg = 'Http request to Bridge was successful: ' . $message . ' (status: ' . $statusCode . ')';
        }

        return $userMsg;
    }

    /**
     * Determine which layer to target for transition based on the currentValue and the min/max values of the notch field
     *
     * @param {object} matchingField
     * @returns {{startValue: float, value: float}}
     */
    protected function determineLayerValues($matchingField) : array
    {
        $fieldMeta = $matchingField['floatMeta'];
            $fromStartValue = $this->getFieldCurrentValue($matchingField);
            $toValue = $fromStartValue === $fieldMeta['min'] ? $fieldMeta['max'] : $fieldMeta['min'];

            return ['startValue'=> $fromStartValue, 'value' => $toValue];
    }

    /**
     * Find the `currentValue` of the given `field` based on the `fieldValueType`
     *
     * @param {object} field
     * @param {string} fieldValueType - floatValue, stringValue, or resourceValue
     *
     * @returns {mixed} - depends on the fieldValueType
     */
    protected function getFieldCurrentValue($field, $fieldValueType='floatValue') {
        // ray('current notch value: '.$field[$fieldValueType]['currentValue']);
            // TODO: resource (and maybe string?) does not have `currentValue`
            //       resource current value IS whatever the field's `resourceValue` is
        return $field[$fieldValueType]['currentValue'];
    }

    /**
     * Find the given `fieldName` in the set of `currentPatches` and return its `floatValue.currentValue`
     *
     * @param {array} currentPatches
     * @param {string} fieldName
     * @param {object} patch
     *
     * @returns {object|null} - the matching field, or null
     */
    protected function findFieldOfPatchInPatches($currentPatches, $fieldName, $patch)
    {
        // find patches that match the addresses & names of the patches we're sending
        $currentField=null;
        $matchingCurrentPatch = array_filter($currentPatches, function ($p) use ($patch, $fieldName, &$currentField){
            if($p['address'] !== $patch['address']){
                return false;
            }

            // see if a field matches
            $field = Arr::first($p['fields'], function ($field) use ($fieldName, &$currentField){
                if($field['name'] === $fieldName){
                    $currentField = $field;
                    return true;
                }
                return false;
            }, null);

            return isset($field);
        });

        return $currentField;
    }

    protected function mainWallIsChanging(array $fieldChanges, string $pattern=null) : bool
    {
        $pattern ??= self::MAIN_WALL_ADDRESS_PATTERN;
        $keyMatch = Arr::first(array_keys($fieldChanges), function ($key) use ($pattern){
            return is_string($pattern)
                ? Str::contains(Str::lower($key), Str::lower($pattern))
                : preg_match($pattern, Str::lower($key));
        }, null);
        return (bool)$keyMatch;
    }

    protected function parsePatchesForMainWall($currentPatches) : array
    {
        // find main wall patches -- address has "Main Wall" in it;
        //  i.e., "Main Wall Notch", "Main Wall - Video A", "Main Wall - Video B"
        $mainWallPatches = $this->findMainWallPatches($currentPatches);

        // get main wall notch layer
        $notchLayer = Arr::first($mainWallPatches, function ($patch) {
            $notchField = Arr::first($patch['fields'], function ($field) {
                return $this->isNotchField($field);
            }, null);
            return isset($notchField);
        }, null);


        return ['mainWallPatches' => $mainWallPatches, 'notchLayer' => $notchLayer];
    }

    /**
     * Find patches in the given array where patch.address indicates it's a full wall cover layer
     * (i.e., could span and obscure multiple layers on the D3 screen)
     *
     * @param {array} searchPatches
     *
     * @return array
     */
    protected function findMainWallPatches(array $searchPatches) : array
    {
        // TODO: define the pattern more globally
    return $this->findPatchesByAddressPattern($searchPatches, self::MAIN_WALL_ADDRESS_PATTERN);
    }

    /**
     *
     * @param {array} searchPatches
     * @param {string|RegExp} pattern - the regular expression pattern to match against
     *
     * @return {array} - matching patches
     */
    protected function findPatchesByAddressPattern($searchPatches, $pattern) : array
    {
        return array_filter($searchPatches, function ($patch) use ($pattern) {
            // address indicates it's a full wall cover layer
            return is_string($pattern)
                ? Str::contains(Str::lower($patch['address']), Str::lower($pattern))
                : preg_match($pattern, Str::lower($patch['address']));
        });
    }

    protected function parseMainWallLayersForOverlay(array $mainWallPatches, $notchLayer)
    {

        // remove notch layer from main wall patches to get main wall video layers we can search through
        $mainWallVideoLayers = $mainWallPatches;

        // note; I didn't bother converting this to PHP
        /*$notchLayerIndex = $mainWallPatches.indexOf($notchLayer);
        if($notchLayerIndex !== -1){
            $mainWallVideoLayers.splice($notchLayerIndex, 1);
        }*/

        // get main wall video layer that has resourceValue.name LIKE 'overlay'
        return $this->findMainWallOverlayLayer($mainWallVideoLayers);
    }

    protected function findMainWallOverlayLayer(array $mainWallPatches)
    {
        return Arr::first($mainWallPatches, function ($patch) {
            $overlayFields = array_filter($patch['fields'], function ($field) {
                if($field['name'] === 'video' && $field['type'] === 'resource'){
                    // the file currently being used is an overlay
                    // TODO: check this a pattern and define the pattern more globally
                    return Str::contains(Str::lower($field['resourceValue']['name']), self::OVERLAY_FILENAME_PATTERN);
                }
                return false;
            });

            return sizeof($overlayFields) > 0;
        });
    }

    /**
     * Determine whether the given notch layer is "displaying" the given video layer
     *
     * @param {object} notchLayer
     * @param {object} videoLayer
     * @returns {boolean}
     */
    protected function isNotchVideoLayerVisible($notchLayer, $videoLayer) : bool
    {
        $visibleSuffix = $this->visibleLayerSuffix($notchLayer);
        $overlaySuffix = substr(trim($videoLayer['address']), -1);

        return $visibleSuffix === $overlaySuffix;
    }

    /**
     * Determine whether the given notch layer is displaying Layer "A" or Layer "B",
     * based on the notch meta's min and max values
     */
    protected function visibleLayerSuffix($notchLayer) : string
    {
        // get notch layer's currentValue
        // min of floatMeta for $notchLayer --> this is A's value
        // max of floatMeta for $notchLayer --> this is B's value
        $notchField = $this->findNotchFieldInLayer($notchLayer);

        return $notchField['floatValue']['currentValue'] === $notchField['floatMeta']['min'] ? 'A' : 'B';
    }

    protected function findNotchFieldInLayer($notchLayer)
    {
        return Arr::first($notchLayer['fields'], function ($field) {
            return $this->isNotchField($field);
        });
    }

    /**
     * Find the notch-related video layer that is currently NOT visible (and not the notch layer)
     * and is therefore the address that should be targeted for changes
     *
     * @param {array} notchPatches
     * @param {object} notchLayer
     * @returns {object} - visible layer
     */
    protected function findTargetNotchVideoLayer($notchPatches, $notchLayer){

        $visibleLayerSuffix = $this->visibleLayerSuffix($notchLayer);

        return Arr::first($notchPatches, function ($patch) use ($visibleLayerSuffix, $notchLayer){
            return substr(trim($patch['address']), -1) !== $visibleLayerSuffix
                && $patch['address'] !== $notchLayer['address'];
        });
    }

    /**
     * This has currently deviated from the JS version since it's using Bridge HTTP not socket
     * @throws \Exception
     */
    protected function getResourcesByType($channel)
    {
        // ray($httpResponse)->label('getResourcesByType -- $httpResponse from:: http://'.$this->BRIDGE_HOST.':'.$this->BRIDGE_PORT.'/v1/resources?type=VideoClip')->green();

        // sample from bridge http function using Postman
        // $httpResponse = '{"channel":"HTTP","sender":"G9HgCrCbJy1lWW7gAAAs","message":{"sender":"G9HgCrCbJy1lWW7gAAAs","url":["/api/v1/resources?type=VideoClip"],"verb":"GET","message_id":"999","response":["{\"result\":[{\"uid\":\"2326559047484556009\",\"name\":\"videoin_2.mov\",\"path\":\"objects/videoclip/videoin_2.mov\",\"type\":\"VideoClip\"},{\"uid\":\"11647684001062717320\",\"name\":\"turtle-on-a-deck.jpg\",\"path\":\"objects/videoclip/portafiles/turtle-on-a-deck.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"4673683578316096322\",\"name\":\"wp9436559.jpg\",\"path\":\"objects/videoclip/main wall graphics/wp9436559.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"17974187788335314596\",\"name\":\"london-wide-5.png\",\"path\":\"objects/videoclip/main wall graphics/london-wide-5.png\",\"type\":\"VideoClip\"},{\"uid\":\"4229158688430492034\",\"name\":\"transition-a.mov\",\"path\":\"objects/videoclip/transition/transition-a.mov\",\"type\":\"VideoClip\"},{\"uid\":\"6937844220666713897\",\"name\":\"slide-01.png\",\"path\":\"objects/videoclip/holding assets/slides/slide-01.png\",\"type\":\"VideoClip\"},{\"uid\":\"8461816508736481029\",\"name\":\"slide-02.png\",\"path\":\"objects/videoclip/holding assets/slides/slide-02.png\",\"type\":\"VideoClip\"},{\"uid\":\"12484303666502586310\",\"name\":\"ledwall_pri_news_overlay.png\",\"path\":\"objects/videoclip/holding assets/ledwall_pri_news_overlay.png\",\"type\":\"VideoClip\"},{\"uid\":\"8842335477648936816\",\"name\":\"d3_test_16-9.png\",\"path\":\"objects/videoclip/test patterns/d3_test_16-9.png\",\"type\":\"VideoClip\"},{\"uid\":\"2922270036083586925\",\"name\":\"turtle-in-the-sea.jpg\",\"path\":\"objects/videoclip/portafiles/turtle-in-the-sea.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"6206524747315314108\",\"name\":\"kitten4-pexels-guillaume-meurice-1317844.jpg\",\"path\":\"objects/videoclip/portafiles/kitten4-pexels-guillaume-meurice-1317844.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"17271302030725603781\",\"name\":\"slide-04.png\",\"path\":\"objects/videoclip/holding assets/slides/slide-04.png\",\"type\":\"VideoClip\"},{\"uid\":\"2343210051579723468\",\"name\":\"videoin_1.mov\",\"path\":\"objects/videoclip/videoin_1.mov\",\"type\":\"VideoClip\"},{\"uid\":\"2344511873348660642\",\"name\":\"videoin_3.mov\",\"path\":\"objects/videoclip/videoin_3.mov\",\"type\":\"VideoClip\"},{\"uid\":\"2325996097531858102\",\"name\":\"videoin_4.mov\",\"path\":\"objects/videoclip/videoin_4.mov\",\"type\":\"VideoClip\"},{\"uid\":\"2849229824220897410\",\"name\":\"live-png-transparent.png\",\"path\":\"objects/videoclip/graphics and bugs/live-png-transparent.png\",\"type\":\"VideoClip\"},{\"uid\":\"80721169407532916\",\"name\":\"d3_test_4-3.png\",\"path\":\"objects/videoclip/test patterns/d3_test_4-3.png\",\"type\":\"VideoClip\"},{\"uid\":\"13212830747920323823\",\"name\":\"sharesub-istockphoto-1439709160-170667a.jpg\",\"path\":\"objects/videoclip/portafiles/sharesub-istockphoto-1439709160-170667a.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"17153719057160804215\",\"name\":\"sharesub-pexels-photo-3726314.jpeg\",\"path\":\"objects/videoclip/portafiles/sharesub-pexels-photo-3726314.jpeg\",\"type\":\"VideoClip\"},{\"uid\":\"2658589132314077794\",\"name\":\"d3_color_4-3.png\",\"path\":\"objects/videoclip/test patterns/d3_color_4-3.png\",\"type\":\"VideoClip\"},{\"uid\":\"7162623943664306242\",\"name\":\"d3_color_16-9.png\",\"path\":\"objects/videoclip/test patterns/d3_color_16-9.png\",\"type\":\"VideoClip\"},{\"uid\":\"15035744120590389183\",\"name\":\"d3_bpc_16-9.d\",\"path\":\"objects/videoclip/test patterns/d3_bpc_16-9.d\",\"type\":\"VideoClip\"},{\"uid\":\"3313837401629176772\",\"name\":\"the-cutest-puppy.jpg\",\"path\":\"objects/videoclip/portafiles/the-cutest-puppy.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"15758919928955863218\",\"name\":\"sharesub-aussie-pup.jpg\",\"path\":\"objects/videoclip/portafiles/sharesub-aussie-pup.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"267194943407739257\",\"name\":\"envie_evasion_logo2_1024_hapalpha.mov\",\"path\":\"objects/videoclip/add_logo/add_logo/envie_evasion_logo2_1024_hapalpha.mov\",\"type\":\"VideoClip\"},{\"uid\":\"1255947929713300558\",\"name\":\"front-blue-lens-flare-4.png\",\"path\":\"objects/videoclip/graphics and bugs/front-blue-lens-flare-4.png\",\"type\":\"VideoClip\"},{\"uid\":\"2341859424057749388\",\"name\":\"bg_for_fillkey_hdvg2.png\",\"path\":\"objects/videoclip/graphics and bugs/bg_for_fillkey_hdvg2.png\",\"type\":\"VideoClip\"},{\"uid\":\"16869771155894875374\",\"name\":\"slide-05.png\",\"path\":\"objects/videoclip/holding assets/slides/slide-05.png\",\"type\":\"VideoClip\"},{\"uid\":\"16459670678340266810\",\"name\":\"screens-02.png\",\"path\":\"objects/videoclip/holding assets/screen/screens-02.png\",\"type\":\"VideoClip\"},{\"uid\":\"11582699972807536044\",\"name\":\"hdvg2_logo_terre_augmentee_hapalpha.mov\",\"path\":\"objects/videoclip/add_logo/hdvg2_logo_terre_augmentee_hapalpha.mov\",\"type\":\"VideoClip\"},{\"uid\":\"12753684719698258202\",\"name\":\"ledwall_vsp.png\",\"path\":\"objects/videoclip/holding assets/ledwall_vsp.png\",\"type\":\"VideoClip\"},{\"uid\":\"16020074309871928760\",\"name\":\"le13h_a_vos_cotes_logo.mov\",\"path\":\"objects/videoclip/add_logo/le13h_a_vos_cotes_logo.mov\",\"type\":\"VideoClip\"},{\"uid\":\"5166037603641070360\",\"name\":\"slide-03.png\",\"path\":\"objects/videoclip/holding assets/slides/slide-03.png\",\"type\":\"VideoClip\"},{\"uid\":\"11841798054415258312\",\"name\":\"envie_evasion_logo2_1024_hapalpha.mov\",\"path\":\"objects/videoclip/add_logo/envie_evasion_logo2_1024_hapalpha.mov\",\"type\":\"VideoClip\"},{\"uid\":\"2473804299854722901\",\"name\":\"london-wide-4.png\",\"path\":\"objects/videoclip/main wall graphics/london-wide-4.png\",\"type\":\"VideoClip\"},{\"uid\":\"16141009613859532058\",\"name\":\"london-wide-3.png\",\"path\":\"objects/videoclip/main wall graphics/london-wide-3.png\",\"type\":\"VideoClip\"},{\"uid\":\"13965258358901605792\",\"name\":\"london-wide-2.png\",\"path\":\"objects/videoclip/main wall graphics/london-wide-2.png\",\"type\":\"VideoClip\"},{\"uid\":\"305132364172143339\",\"name\":\"london-wide-1.png\",\"path\":\"objects/videoclip/main wall graphics/london-wide-1.png\",\"type\":\"VideoClip\"},{\"uid\":\"4523161971166344990\",\"name\":\"screens-04.png\",\"path\":\"objects/videoclip/holding assets/screen/screens-04.png\",\"type\":\"VideoClip\"},{\"uid\":\"18345714037136499865\",\"name\":\"screens-03.png\",\"path\":\"objects/videoclip/holding assets/screen/screens-03.png\",\"type\":\"VideoClip\"},{\"uid\":\"14272941766178474909\",\"name\":\"logo_13h_a_table_hapalpha.mov\",\"path\":\"objects/videoclip/add_logo/logo_13h_a_table_hapalpha.mov\",\"type\":\"VideoClip\"},{\"uid\":\"3369030275824369178\",\"name\":\"majesticpenguin.jpg\",\"path\":\"objects/videoclip/portafiles/majesticpenguin.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"7098996892590361697\",\"name\":\"kitten5-pexels-katarzyna-modrzejewska-1314550.jpg\",\"path\":\"objects/videoclip/portafiles/kitten5-pexels-katarzyna-modrzejewska-1314550.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"16568069134314065371\",\"name\":\"turtle-lookin-at-you.jpg\",\"path\":\"objects/videoclip/portafiles/turtle-lookin-at-you.jpg\",\"type\":\"VideoClip\"},{\"uid\":\"14901695553844742737\",\"name\":\"penguinflappyboi.jpg\",\"path\":\"objects/videoclip/portafiles/penguinflappyboi.jpg\",\"type\":\"VideoClip\"}]}"]}}';

        $resourcesByType = [];
        $httpResponse = $this->makeBridgeHttpRequest("/api/v1/resources?type=VideoClip");
        $results = $this->parseBridgeHttpResponse($httpResponse);

        foreach($results as $resource) {
            $resourcesByType[$resource['type']][$resource['uid']] = $resource['name']  .  ' ('  .  $resource['path']  .  ')';
        }

        // sort resources alphabetically by value within their type
        foreach($resourcesByType as $resourceType => $resources) {
            asort($resourcesByType[$resourceType], SORT_LOCALE_STRING );

            $sorted = [];
            foreach($resourcesByType[$resourceType] as $sdata) {
                $sorted []= [
                    'uid' => $sdata[0], 'name' => $sdata[1]
                ];
            };

            $resourcesByType[$resourceType] = $sorted;
        }

        /*foreach($responses as $ind => $response){
            // responses.forEach(function callback(response, ind) {
            if ($ind < sizeof($response)) {
                // /resources?type=____ result --> map resourceTypes to the resources returned
                $rtype = $response[$ind];
                $resources[$rtype] = [];
                $response = json_decode($response);
                foreach ($response['result'] as $resource) {
                    if ($resource['type'] === $rtype){
                        $resources[$rtype][$resource['uid']] = $resource['name']  .  ' ('  .  $resource['path']  .  ')';
                    }
                }
            }
            else {
                // /GetShareFilesDetailed result
                /* detailed resp Ex:
                    [
                        {
                            "Name":"force latest rev.png",
                            "Path":"C:\\Users\\jessica.edmonds\\Documents\\Lightshot",
                            "Extension":".png",
                            "ThumbnailBase64":"/9j/4AAQSkZJRgABAQEAYABgAAD..."
                        },
                        ...
                    ]
                *

                try {
                    $fileDetails = json_decode(trim($response));
                    if (isset($fileDetails)){
                        foreach($fileDetails as $fileDetail){
                            $displayName = $fileDetail['Name']  .  ' ('  .  $fileDetail['Path']  .  ')';
                            $shared_resources []= [
                                'uid' => $fileDetail['Path'] . '/' . $fileDetail['Name'],
                                'name' => $displayName,
                                'thumbnail64' => $fileDetail['ThumbnailBase64'],
                                'path' => $fileDetail['Path']
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // console.warn(`Parsing Bridge Response for ${responses[$ind]} FAILED: `, {response}, {e});
                }
            }

            // sort resources within their type
            foreach($response as $resourceType) {
                if ($resources[$resourceType]) {

                    sort($resources[$resourceType]);

                    $sorted = [];
                    foreach($resources[$resourceType] as $sdata) {
                        $sorted []= [
                            'uid' => $sdata[0], 'name' => $sdata[1]
                        ];
                    };

                    $resources[$resourceType] = $sorted;
                }
            }

        } // end each $responses */


        $data['resources'] = $resourcesByType;
        // $data['shared_resources'] = $shared_resources;

        // ray('getResourcesByType ==> sorted data', $data);

        return $data;
    }

    /**
     * Make an HTTP request to the D3 Porta Bridge and increment the `message_id`.
     * Defaults to returning the response as JSON, but can return the
     * raw response with $raw=true
     *
     * @param string|array $urlData - bridge expects an array of urls+data, if POST request, then array like -->
     *                              [['url-to-request-1', 'data-1'], ['url-to-request-2', 'data-2'], ...]
     *                                  - For GET requests, can just pass in the url string and wrapper array will be
     *                                    created
     * @param string       $verb    - defaults to 'GET'
     * @param string       $protocol - defaults to 'http'
     * @param string|null  $host    - defaults to self::BRIDGE_HOST
     * @param string|null  $port    - defaults to self::BRIDGE_PORT
     * @param string|null $path     - URL path to append to the host:port,
     *                                  - defaults to self::BRIDGE_HTTP_ENDPOINT,
     *                                  - should NOT include prepended '/'
     * @param bool         $raw     - return the raw response instead of the parsed JSON
     *
     * @return mixed
     * @throws \Exception
     */
    public function makeBridgeHttpRequest(
        string|array $urlData,
        string $verb='GET',
        string $protocol='http',
        string $host=null,
        string $port=null,
        string $path=null,
        bool $raw=false
    ) : mixed
    {
        // Log::debug("host arg: $host, BRIDGE_HOST: $this->BRIDGE_HOST");

        $host ??= $this->BRIDGE_HOST;
        $port ??= $this->BRIDGE_PORT;
        $path ??= self::BRIDGE_HTTP_ENDPOINT;
        $urlData = is_string($urlData) ? [$urlData] : $urlData;

        // Log::debug("host used: $host");

        $this->nextId++;
        $payload = json_encode(['url'=> $urlData, 'verb'=> $verb, 'message_id'=> $this->nextId]);

        try {
            $response = Http::withBody(
                $payload,
                'application/json'
            )->$verb($protocol . '://' . $host . ':' . $port . '/' . $path);

            if($response->failed()) {
                // there was a problem with the request between Porta and the Bridge's listener
                Log::error('Http request (message_id: ' . $this->nextId . ') to Bridge failed before being processed: ' . $response->status() . ' -- ' . $response->body());
                throw new \Exception('Http request (message_id: ' . $this->nextId . ') to Bridge failed before being processed: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Http request (message_id: ' . $this->nextId . ') to Bridge failed: ' . $e->getMessage());
            throw new \Exception('Http request (message_id: ' . $this->nextId . ') to Bridge failed: ' . $e->getMessage());
        }

        return $raw ? $response : $response->json();
    }
}
