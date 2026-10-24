<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TtsController extends Controller
{
    // Synthesizes Sinhala speech via Google Cloud Text-to-Speech and returns base64 MP3 audio.
    // Results are cached by text so repeated bill totals don't re-hit the API.
    public function synthesize(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:500',
        ]);

        $apiKey = config('services.google_tts.key');
        if (!$apiKey) {
            return response()->json(['message' => 'Google TTS API key is not configured'], 500);
        }

        $text = $request->input('text');
        $cacheKey = 'tts_si_' . md5($text);

        $audioContent = Cache::remember($cacheKey, now()->addDays(30), function () use ($text, $apiKey) {
            $response = Http::timeout(15)->post(
                'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $apiKey,
                [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => 'si-LK',
                        'ssmlGender' => 'FEMALE',
                    ],
                    'audioConfig' => [
                        'audioEncoding' => 'MP3',
                    ],
                ]
            );

            if (!$response->successful()) {
                abort(502, 'Google TTS request failed: ' . $response->body());
            }

            return $response->json('audioContent');
        });

        return response()->json(['audioContent' => $audioContent]);
    }
}
