<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class RecordingChunkController extends Controller
{
    public function storeChunk(Request $request)
    {
        $request->validate([
            'recording_id' => 'required',
            'index' => 'required|integer',
            'chunk' => 'required|file'
        ]);

        $recordingId = $request->input('recording_id');
        $index = $request->input('index');
        $path = "temp_recordings/{$recordingId}";
        Storage::makeDirectory($path);
        $request->file('chunk')->storeAs($path, "chunk_{$index}.webm");

        return response()->json(['status' => 'ok']);
    }

    public function concatChunks(Request $request)
    {
        $request->validate([
            'recording_id' => 'required'
        ]);

        $recordingId = $request->input('recording_id');
        $dir = storage_path("app/temp_recordings/{$recordingId}");
        if (!is_dir($dir)) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $files = collect(scandir($dir))
            ->filter(fn($f) => str_ends_with($f, '.webm'))
            ->sort()
            ->values();

        $listPath = "$dir/list.txt";
        $listContent = $files->map(fn($f) => "file '$dir/$f'")->implode(PHP_EOL);
        file_put_contents($listPath, $listContent);

        $outputPath = "$dir/output.webm";
        $process = new Process(['ffmpeg', '-f', 'concat', '-safe', '0', '-i', $listPath, '-c', 'copy', $outputPath]);
        $process->run();

        if (!$process->isSuccessful()) {
            return response()->json(['error' => $process->getErrorOutput()], 500);
        }

        $contents = file_get_contents($outputPath);
        return response($contents, 200)->header('Content-Type', 'audio/webm');
    }
}
