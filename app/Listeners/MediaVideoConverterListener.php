<?php

namespace App\Listeners;

use App\Services\FileService;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\Helpers\File as MediaLibraryFileHelper;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaVideoConverterListener implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    protected Media $media;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(protected FileService $fileService)
    {

    }

    public function handle(MediaHasBeenAdded $event) : void
    {
        $media = $event->media;
        if (!$this->isVideo($media)
            || strtolower($media->extension) === 'mp4'
            || strtolower($media->mime_type) === 'video/mp4'
        ) {
            return;
        }

        // prevent model events from firing
        $event->media->flushEventListeners();

        $fullPath = $media->getPath();
        $newFileFullPath = pathinfo($fullPath, PATHINFO_DIRNAME)
            . DIRECTORY_SEPARATOR . pathinfo($fullPath, PATHINFO_FILENAME)
            . '.mp4';

        $media->setCustomProperty('status', 'PROCESSING');
        $media->save();

        try {
            if (file_exists($newFileFullPath)) {
                unlink($newFileFullPath);
            }

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => config('media-library.ffmpeg_path'),
                'ffprobe.binaries' => config('media-library.ffprobe_path'),
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);

            // debugging FFMPEG
            // $ffmpeg->getFFMpegDriver()->listen(new \Alchemy\BinaryDriver\Listeners\DebugListener());
            // $ffmpeg->getFFMpegDriver()->on('debug', function ($message) {
            //     Log::debug("FFMPEG debugging: ".$message."\n");
            // });
            // end debugging FFMPEG

            $video = $ffmpeg->open($fullPath);

            $format = new X264();

            $format->on('progress', function ($video, $format, $percentage) use ($media, $fullPath, $newFileFullPath) {
                if (!($percentage % 10)) {
                    Log::debug("MEDIA CONVERTING ($percentage) for '$fullPath' to '$newFileFullPath'");

                    $media->setCustomProperty('progress', $percentage);
                    $media->save();
                }
            });

            // `libvo_aacenc` codec no longer supported, using default `aac` codec from X264()
            $format->setKiloBitrate(1000)
                ->setAudioChannels(2)
                ->setAudioKiloBitrate(128);

            $video->save($format, $newFileFullPath);
            $this->mediaConvertingCompleted($media, $fullPath, $newFileFullPath);

        } catch (\Exception $e) {
            Log::error("Conversion failed for media id: {$media->id} with error: {$e->getMessage()} in file {$e->getFile()} on line {$e->getLine()}");
            $media->setCustomProperty('status', 'FAILED');
            $media->setCustomProperty('error', $e->getMessage());
            $media->save();
        }
    }

    /**
     * @param Media  $media
     * @param string $originalFilePath
     * @param string $convertedFilePath
     */
    protected function mediaConvertingCompleted(Media $media, string $originalFilePath, string $convertedFilePath) : void
    {
        Log::info("Custom Media conversion completed for '$originalFilePath' to '$convertedFilePath'");

        $media->setCustomProperty('status', 'READY');
        $media->setCustomProperty('progress', 100);
        $media->save();

        // the conversion already exists in the correct directory so don't create a Media model for it, we're done
    }


    /**
     * Is media a video?
     *
     * @return bool
     */
    protected function isVideo(Media $media) : bool
    {
        return (Str::contains($media->mime_type, 'video'));
    }
}
