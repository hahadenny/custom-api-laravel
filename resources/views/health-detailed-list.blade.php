<html lang="en" class="{{$theme == 'dark' ? 'dark' : ''}}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('health::notifications.health_results') }}</title>
    @if(!config('app.onprem'))
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    @endif
    {{$assets}}
</head>

<body class="antialiased bg-gray-100 mt-7 md:mt-12 dark:bg-gray-900">
<div class="mx-auto max-w-7xl lg:px-8 sm:px-6">
    <div class="flex flex-wrap justify-center space-y-3">
        <h4 class="w-full text-2xl font-bold text-center text-gray-900 dark:text-white">{{ __('health::notifications.laravel_health') }}</h4>
        <div class="flex justify-center w-full">
            <x-health-logo/>
        </div>
        @if ($lastRanAt)
            <div class="{{ $lastRanAt->diffInMinutes() > 5 ? 'text-red-400' : 'text-gray-400 dark:text-gray-500' }} text-sm text-center font-medium">
                {{ __('health::notifications.check_results_from') }} {{ $lastRanAt->diffForHumans()." (".$lastRanAt->format("F j, Y h:i:s A e").")" }}
            </div>
        @endif
        <p class="flex justify-center w-full text-gray-500 dark:text-gray-600 text-sm text-center font-medium" style="font-style:italic">
            Dashboard page last refreshed: {{ (new DateTime())->format("F j, Y h:i:s A e") }}
        </p>
    </div>
    <div class="px-2 mt-6 md:mt-8 md:px-0">
        @if (count($checkResults?->storedCheckResults ?? []))
            <dl class=" grid grid-cols-1 gap-2.5 sm:gap-3 md:gap-5 md:grid-cols-2">
                @foreach ($checkResults->storedCheckResults as $result)
                    <div class="flex items-start px-4 space-x-2 overflow-hidden py-5 text-opacity-0 transition transform bg-white shadow-md shadow-gray-200 dark:shadow-black/25 dark:shadow-md dark:bg-gray-800 rounded-xl sm:p-6 md:space-x-3 md:min-h-[130px] dark:border-t dark:border-gray-700">
                        <x-health-status-indicator :result="$result" />
                        <div class="w-full">
                            <dd class="-mt-1 font-bold text-gray-900 dark:text-white md:mt-1 md:text-xl">
                                {{ $result->label }}
                            </dd>
                            <dt class="mt-0 text-sm font-medium text-gray-600 dark:text-gray-300 md:mt-1">
                                @if (!empty($result->notificationMessage))
                                    {{ $result->notificationMessage }}
                                @else
                                    {{ $result->shortSummary }}
                                @endif
                            </dt>
                            @if (!empty($result->meta))
                                @php
                                    $meta = json_decode(json_encode($result->meta), true);
                                @endphp
                                <p class="block w-full text-md font-bold italic text-gray-900 dark:text-white md:mt-1 md:text-lg" style="border-top: 1px solid #111827; font-style:italic; margin-top: 1.5em; padding-bottom: .75em; padding-top: .75em">Details</p>
                                <dl class="flex flex-wrap justify-between align-top text-left mt-0 text-sm font-medium text-gray-600 dark:text-gray-300 md:mt-1">
                                    @if(is_numeric(key($meta)))
                                        {{-- Numeric key means it's an array of arrays, i.e., group repl list --}}
                                        @foreach($meta as $i => $array)
                                            <p class="text-md font-bold italic text-gray-900 dark:text-white md:mt-1 md:text-lg" style="font-style:italic; margin-top: .75em; padding-bottom: .5em;">Machine Details</p>
                                            <dl class="flex flex-wrap justify-between align-top text-left text-sm font-medium text-gray-600 dark:text-gray-300 md:mt-2">
                                                @foreach($array as $key => $value)
                                                    <dt class="w-1/2 font-bold" style="margin-bottom:1em">
                                                        {{ $key }}
                                                    </dt>
                                                    <dd class="w-1/2" style="margin-bottom:1em">
                                                        @if(is_array($value))
                                                            <dl class="flex flex-wrap justify-between align-top text-left text-sm font-medium text-gray-600 dark:text-gray-300 md:mt-2">
                                                                @foreach($value as $innerKey => $innerValue)
                                                                    <dt class="w-1/2 font-bold" style="margin-bottom:1em">
                                                                        {{ $innerKey }}
                                                                    </dt>
                                                                    <dd class="w-1/2" style="margin-bottom:1em">
                                                                        @if(is_array($innerValue))
                                                                            {{ json_encode($innerValue) }}
                                                                        @else
                                                                            {{ $innerValue }}
                                                                        @endif
                                                                    </dd>
                                                                @endforeach
                                                            </dl>
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </dd>
                                                @endforeach
                                            </dl>
                                        @endforeach
                                    @else
                                        @foreach($meta as $key => $value)
                                            <dt class="w-1/2 font-bold" style="margin-bottom:1em">
                                                {{ $key }}
                                            </dt>
                                            <dd class="w-1/2" style="margin-bottom:1em">
                                                @if(is_array($value))
                                                    <dl class="flex flex-wrap justify-between align-top text-left text-sm font-medium text-gray-600 dark:text-gray-300 md:mt-2">
                                                        @foreach($value as $innerKey => $innerValue)
                                                            <dt class="w-1/2 font-bold" style="margin-bottom:1em">
                                                                {{ $innerKey }}
                                                            </dt>
                                                            <dd class="w-1/2" style="margin-bottom:1em">
                                                                @if(is_array($innerValue))
                                                                    {{ json_encode($innerValue) }}
                                                                @else
                                                                    {{ $innerValue }}
                                                                @endif
                                                            </dd>
                                                        @endforeach
                                                    </dl>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        @endforeach
                                    @endif
                                </dl>
                            @endif
                        </div>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
</div>
</body>
</html>
