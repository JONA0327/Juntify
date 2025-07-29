<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;

it('returns 500 when ASSEMBLYAI_API_KEY is missing', function () {
    Config::set('services.assemblyai.api_key', null);

    $response = $this->post('/transcription', [
        'audio' => UploadedFile::fake()->create('audio.mp3', 10),
    ]);

    $response->assertStatus(500)
             ->assertJson(['error' => 'AssemblyAI API key missing']);
});

