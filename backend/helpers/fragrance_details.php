<?php

function fragrance_clean_optional(mixed $value): ?string
{
    if (is_array($value)) {
        $value = implode(', ', array_filter(array_map('trim', $value)));
    }

    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function fragrance_normalize_details(array $details): array
{
    return [
        'top_notes'   => fragrance_clean_optional($details['top_notes'] ?? null),
        'heart_notes' => fragrance_clean_optional($details['heart_notes'] ?? null),
        'base_notes'  => fragrance_clean_optional($details['base_notes'] ?? null),
        'description' => fragrance_clean_optional($details['description'] ?? null),
    ];
}

function fragrance_notes_missing(array $details): bool
{
    $details = fragrance_normalize_details($details);
    return empty($details['top_notes'])
        || empty($details['heart_notes'])
        || empty($details['base_notes']);
}

/** @deprecated Use fragrance_notes_missing() - kept for back-compat */
function fragrance_details_missing(array $details): bool
{
    return fragrance_notes_missing($details);
}

function fragrance_fallback_details(string $name, string $brand = ''): array
{
    $label = trim($name . ($brand !== '' ? " by {$brand}" : ''));

    return [
        'top_notes'   => 'Bergamote, Poivre Rose, Notes Aromatiques',
        'heart_notes' => 'Jasmin, Lavande, Accords Boises',
        'base_notes'  => 'Ambre, Musc, Vanille',
        'description' => "{$label} revele une signature elegante et raffinee, construite autour d'accords lumineux, floraux et ambres.",
    ];
}

function fragrance_lookup_with_gemini(string $name, string $brand = '', string $apiKey = ''): array
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return ['details' => [], 'error' => 'Gemini API key is not configured.'];
    }

    if (!function_exists('curl_init')) {
        return ['details' => [], 'error' => 'PHP cURL extension is not enabled.'];
    }

    $perfumeName = trim($name . ($brand !== '' ? " by {$brand}" : ''));
    $prompt = <<<PROMPT
You are a professional fragrance encyclopedia. I will give you a perfume name and brand.
Return ONLY a valid JSON object with exactly these 4 fields:
- "top_notes": comma-separated list of top notes in French
- "heart_notes": comma-separated list of heart notes in French
- "base_notes": comma-separated list of base notes in French
- "description": a 1-2 sentence poetic description in French of the fragrance character

If this is a real well-known perfume, use accurate real notes.
If completely unknown, invent plausible notes that match the name/brand style.
Do NOT include any explanation, only the JSON object.

Perfume: {$perfumeName}
PROMPT;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . rawurlencode($apiKey);
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [['text' => $prompt]],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 300,
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['details' => [], 'error' => 'Network error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        $error = json_decode((string) $response, true);
        $message = $error['error']['message'] ?? "HTTP {$httpCode}";
        return ['details' => [], 'error' => 'Gemini API error: ' . $message];
    }

    $gemini = json_decode((string) $response, true);
    $text = $gemini['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
    $text = preg_replace('/```\s*$/', '', $text);

    $notes = json_decode(trim((string) $text), true);
    if (!$notes && preg_match('/\{.*\}/s', (string) $text, $match)) {
        $notes = json_decode($match[0], true);
    }

    if (!is_array($notes)) {
        return ['details' => [], 'error' => 'Could not parse Gemini response.'];
    }

    return ['details' => fragrance_normalize_details($notes), 'error' => null];
}

function fragrance_complete_details(string $name, string $brand, array $submitted, bool $allowFallback = true): array
{
    $details = fragrance_normalize_details($submitted);
    $source = 'manual';
    $warning = null;

  
    if (fragrance_notes_missing($details)) {
        $lookup = fragrance_lookup_with_gemini($name, $brand, defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        $aiDetails = fragrance_normalize_details($lookup['details'] ?? []);

        foreach ($details as $key => $value) {
           
            if (empty($value) && !empty($aiDetails[$key])) {
                $details[$key] = $aiDetails[$key];
                $source = 'gemini';
            }
        }

        $warning = $lookup['error'] ?? null;
    }

   
    if ($allowFallback && fragrance_notes_missing($details)) {
        $fallback = fragrance_fallback_details($name, $brand);
        foreach ($details as $key => $value) {
            if (empty($value)) {
                $details[$key] = $fallback[$key];
            }
        }
        $source = $source === 'gemini' ? 'gemini+fallback' : 'fallback';
    }

    
    if (empty($details['description'])) {
        $label = trim($name . ($brand !== '' ? " by {$brand}" : ''));
        $details['description'] = "{$label} revele une signature elegante et raffinee, construite autour d'accords lumineux, floraux et ambres.";
    }

    return [
        'details' => $details,
        'source'  => $source,
        'warning' => $warning,
    ];
}
