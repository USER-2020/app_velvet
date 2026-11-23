<?php

return [
    'api_key' => env('GROQ_API_KEY'),
    'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    'default_model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
    'models' => array_values(array_filter(array_map('trim', explode(',', env('GROQ_MODELS', 'llama-3.1-8b-instant,openai/gpt-oss-20b'))))),
    'timeout' => (int) env('GROQ_TIMEOUT_SECONDS', 30),
    'max_history' => (int) env('GROQ_MAX_HISTORY', 12),
    'max_input_chars' => (int) env('GROQ_MAX_INPUT_CHARS', 4000),
];
