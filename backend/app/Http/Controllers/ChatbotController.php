<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Services\Chatbot\BookRetrievalService;
use App\Services\Chatbot\PromptBuilder;

class ChatbotController extends Controller
{
    public function ask(Request $request)
    {
        $v = Validator::make($request->all(), [
            'message' => 'required|string|max:800',
            'filters' => 'nullable|array',
            'filters.category' => 'nullable|string|max:100',
            'filters.language' => 'nullable|string|max:50',
            'filters.minPrice' => 'nullable|numeric|min:0',
            'filters.maxPrice' => 'nullable|numeric|min:0',
            'filters.readingAge' => 'nullable|integer|min:0|max:120',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()], 422);
        }

        $message = (string) $request->input('message');
        $filters = (array) $request->input('filters', []);
        $lower = mb_strtolower($message);

        /**
         * =====================================================
         * 0) AUTO PARSE PRICE / AGE FROM USER MESSAGE (EN)
         * =====================================================
         */
        if (!isset($filters['minPrice']) && !isset($filters['maxPrice'])) {
            $p = $this->parsePriceUsd($lower);
            if ($p['min'] !== null) $filters['minPrice'] = $p['min'];
            if ($p['max'] !== null) $filters['maxPrice'] = $p['max'];
        }

        if (!isset($filters['readingAge'])) {
            $age = $this->parseReadingAge($lower);
            if ($age !== null) $filters['readingAge'] = $age;
        }

        /**
         * =====================================================
         * 1) AUTO MAP KEYWORDS → CATEGORY (ENGLISH ONLY)
         * =====================================================
         */
        if (!isset($filters['category'])) {
            if (str_contains($lower, 'finance')) {
                $filters['category'] = 'Finance';

            } elseif (str_contains($lower, 'fiction')) {
                $filters['category'] = 'Fiction';

            } elseif (str_contains($lower, 'fantasy')) {
                $filters['category'] = 'Fantasy';

            } elseif (str_contains($lower, 'romance') || str_contains($lower, 'love story')) {
                $filters['category'] = 'Romance';

            } elseif (str_contains($lower, 'thriller') || str_contains($lower, 'mystery')) {
                $filters['category'] = 'Thriller';

            } elseif (str_contains($lower, 'memoir') || str_contains($lower, 'autobiography')) {
                $filters['category'] = 'Memoir';

            } elseif (str_contains($lower, 'historical') || str_contains($lower, 'history')) {
                $filters['category'] = 'Historical';

            } elseif (str_contains($lower, 'adventure')) {
                $filters['category'] = 'Adventure';

            } elseif (str_contains($lower, 'sociology') || str_contains($lower, 'society')) {
                $filters['category'] = 'Sociology';

            } elseif (str_contains($lower, 'satire') || str_contains($lower, 'political satire')) {
                $filters['category'] = 'Political Satire';
            }
        }

        /**
         * =====================================================
         * 2) RETRIEVAL (NO VECTOR DB)
         * - Retriever will NOT fallback early: it will search by price/category/age properly.
         * =====================================================
         */
        $topN = (int) env('CHATBOT_TOP_N', 8);

        /** @var BookRetrievalService $retriever */
        $retriever = app(BookRetrievalService::class);
        $books = $retriever->search($message, $filters, $topN, 'AUTO');

        if (count($books) === 0) {
            return response()->json([
                "answer" => "Currently, we do not have a suitable book for this topic.",
                "suggestions" => [],
            ]);
        }

        /**
         * =====================================================
         * 3) PROMPT (ENGLISH, CONTEXT-LOCKED)
         * =====================================================
         */
        /** @var PromptBuilder $promptBuilder */
        $promptBuilder = app(PromptBuilder::class);
        $payload = $promptBuilder->build($message, $books);

        $base = rtrim(env('LLAMA_BASE_URL', 'http://llama:8080'), '/');

        /**
         * =====================================================
         * 4) CALL LLM (WITH FALLBACKS)
         * =====================================================
         */
        $answerRaw = null;

        // 4.1) OpenAI-style chat completions
        try {
            $res = Http::timeout((int)env('CHATBOT_TIMEOUT', 30))
                ->post($base . '/v1/chat/completions', array_merge($payload, [
                    "model" => "local",
                ]));

            if ($res->ok()) {
                $json = $res->json();
                $answerRaw = $json['choices'][0]['message']['content'] ?? null;
            }
        } catch (\Throwable $e) {}

        // 4.2) Fallback /v1/completions
        if (!$answerRaw) {
            try {
                $sys = $payload['messages'][0]['content'] ?? '';
                $usr = $payload['messages'][1]['content'] ?? '';
                $prompt = $sys . "\n\n" . $usr . "\n\nASSISTANT:";

                $res = Http::timeout((int)env('CHATBOT_TIMEOUT', 30))
                    ->post($base . '/v1/completions', [
                        "model" => "local",
                        "prompt" => $prompt,
                        "temperature" => 0.1,
                        "max_tokens" => 260,
                    ]);

                if ($res->ok()) {
                    $json = $res->json();
                    $answerRaw = $json['choices'][0]['text'] ?? null;
                }
            } catch (\Throwable $e) {}
        }

        // 4.3) Fallback llama.cpp /completion
        if (!$answerRaw) {
            $sys = $payload['messages'][0]['content'] ?? '';
            $usr = $payload['messages'][1]['content'] ?? '';
            $prompt = $sys . "\n\n" . $usr . "\n\nASSISTANT:";

            $res = Http::timeout((int)env('CHATBOT_TIMEOUT', 60))
                ->post($base . '/completion', [
                    "prompt" => $prompt,
                    "temperature" => 0.1,
                    "n_predict" => 260,
                ]);

            if ($res->ok()) {
                $json = $res->json();
                $answerRaw = $json['content'] ?? ($json['response'] ?? null);
            }
        }

        if (!$answerRaw) {
            return response()->json([
                "answer" => "The recommendation system is currently unavailable. Please try again later.",
                "suggestions" => [],
            ], 500);
        }

        $answerRaw = trim((string)$answerRaw);

        /**
         * =====================================================
         * 5) PARSE JSON (ANSWER + SUGGESTIONS)
         * =====================================================
         */
        $jsonText = $this->extractFirstJsonObject($answerRaw);
        $data = $jsonText ? json_decode($jsonText, true) : null;

        if (is_array($data) && !empty($data['answer'])) {
            $answer = trim((string)$data['answer']);
            $suggestions = [];

            if (!empty($data['suggestions']) && is_array($data['suggestions'])) {
                foreach ($data['suggestions'] as $s) {
                    if (is_array($s) && isset($s['id'])) {
                        $suggestions[] = ['id' => (int)$s['id']];
                    }
                }
            }

            // if model forgot suggestions, generate from top results
            if (count($suggestions) === 0) {
                $suggestions = array_map(fn($b) => ['id' => (int)$b['id']], array_slice($books, 0, 2));
            }

            return response()->json([
                "answer" => $answer,
                "suggestions" => $suggestions,
            ]);
        }

        /**
         * =====================================================
         * 6) FINAL FALLBACK (ALWAYS JSON, NO HALLUCINATION)
         * =====================================================
         */
        $picked = array_slice($books, 0, 2);
        $parts = [];
        $suggestions = [];

        foreach ($picked as $b) {
            $price = isset($b['finalPrice']) ? (float)$b['finalPrice'] : (float)($b['price'] ?? 0);
            $parts[] = $b['title'] . " ($" . number_format($price, 2) . ")";
            $suggestions[] = ["id" => (int)$b['id']];
        }

        return response()->json([
            "answer" => "Based on what we have, I suggest " . implode(" and ", $parts) . ".",
            "suggestions" => $suggestions,
        ]);
    }

    /**
     * Extract the first JSON object from LLM output (robust against extra text / code fences)
     */
    private function extractFirstJsonObject(string $text): ?string
    {
        $t = preg_replace('/```(?:json)?/i', '', $text);
        $t = str_replace('```', '', (string)$t);

        $start = strpos($t, '{');
        if ($start === false) return null;

        $depth = 0;
        $inStr = false;
        $escape = false;

        for ($i = $start; $i < strlen($t); $i++) {
            $ch = $t[$i];

            if ($inStr) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inStr = false;
                }
                continue;
            } else {
                if ($ch === '"') {
                    $inStr = true;
                    continue;
                }
                if ($ch === '{') $depth++;
                if ($ch === '}') $depth--;
                if ($depth === 0) {
                    return substr($t, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Parse USD price intent from user message
     */
    private function parsePriceUsd(string $lower): array
    {
        $min = null;
        $max = null;

        // between 5 and 20 / between $5 and $20
        if (preg_match('/between\s*\$?(\d+(?:\.\d+)?)\s*(?:and|to)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
            return ['min' => (float)$m[1], 'max' => (float)$m[2]];
        }

        // under/below/max/<=
        if (preg_match('/(?:under|below|max(?:imum)?|<=)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
            $max = (float)$m[1];
        }

        // over/above/upper/min/>=
        if (preg_match('/(?:over|above|upper|min(?:imum)?|>=)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
            $min = (float)$m[1];
        }

        // budget around
        if ($min === null && $max === null) {
            if (preg_match('/(?:budget|around)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
                $max = (float)$m[1];
            }
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Parse reading age intent from user message
     */
    private function parseReadingAge(string $lower): ?int
    {
        // "12+" or "age 12"
        if (preg_match('/(?:age\s*)?(\d{1,2})\s*\+/', $lower, $m)) return (int)$m[1];
        if (preg_match('/age\s*(\d{1,2})\b/', $lower, $m)) return (int)$m[1];

        // range "7-10" -> use upper bound
        if (preg_match('/(\d{1,2})\s*-\s*(\d{1,2})/', $lower, $m)) {
            return (int)$m[2];
        }

        // buckets
        if (str_contains($lower, 'kids') || str_contains($lower, 'children')) return 10;
        if (str_contains($lower, 'teen') || str_contains($lower, 'young adult')) return 16;
        if (str_contains($lower, 'adult')) return 18;

        return null;
    }
}
