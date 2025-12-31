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

            // 기존 single age
            'filters.readingAge' => 'nullable|integer|min:0|max:120',

            // NEW: range age
            'filters.minReadingAge' => 'nullable|integer|min:0|max:120',
            'filters.maxReadingAge' => 'nullable|integer|min:0|max:120',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()], 422);
        }

        $message = (string) $request->input('message');
        $filters = (array) $request->input('filters', []);
        $lower = mb_strtolower($message);

        /**
         * =====================================================
         * 0) AUTO PARSE AGE FIRST (EN)
         * - Prefer RANGE (min/max) if user says "14 to 16 years old"
         * - Prevent "around 14 to 16 years old" being parsed as price
         * =====================================================
         */
        if (
            !isset($filters['minReadingAge'])
            && !isset($filters['maxReadingAge'])
            && !isset($filters['readingAge'])
        ) {
            $range = $this->parseReadingAgeRange($lower);

            if ($range !== null) {
                if ($range['min'] !== null) $filters['minReadingAge'] = $range['min'];
                if ($range['max'] !== null) $filters['maxReadingAge'] = $range['max'];
            } else {
                $age = $this->parseReadingAge($lower);
                if ($age !== null) $filters['readingAge'] = $age;
            }
        }

        /**
         * =====================================================
         * 0.1) AUTO PARSE PRICE (ONLY IF CURRENCY HINT EXISTS OR NOT AN AGE MESSAGE)
         * =====================================================
         */
        if (!isset($filters['minPrice']) && !isset($filters['maxPrice'])) {
            $hasCurrencyHint = preg_match('/(\$|usd|dollar|dollars|price|cost|budget)/i', $lower) === 1;
            $hasAgeHint = preg_match('/(years?\s*old|years?|yrs?|age)\b/i', $lower) === 1;

            // If message talks about age and has NO currency hint -> DO NOT parse price
            if (!($hasAgeHint && !$hasCurrencyHint)) {
                $p = $this->parsePriceUsd($lower);
                if ($p['min'] !== null) $filters['minPrice'] = $p['min'];
                if ($p['max'] !== null) $filters['maxPrice'] = $p['max'];
            }
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
         * =====================================================
         */
        $topN = (int) env('CHATBOT_TOP_N', 8);

        /** @var BookRetrievalService $retriever */
        $retriever = app(BookRetrievalService::class);

        $books = $retriever->search($message, $filters, $topN, 'AUTO');

        /**
         * =====================================================
         * 2.1) HARD CONSTRAINT ENFORCEMENT (PRICE / AGE)
         * - For small LLMs, do NOT let the LLM decide "suitable".
         * - If user asked price/age, enforce strictly and respond deterministically.
         * =====================================================
         */
        $hasPrice = isset($filters['minPrice']) || isset($filters['maxPrice']);

        // NEW: range-aware age detection
        $hasAge =
            isset($filters['readingAge'])
            || isset($filters['minReadingAge'])
            || isset($filters['maxReadingAge']);

        if (($hasPrice || $hasAge)) {
            $min = $filters['minPrice'] ?? null;
            $max = $filters['maxPrice'] ?? null;

            // single age (<=)
            $age = $filters['readingAge'] ?? null;

            // NEW: range age
            $minAge = $filters['minReadingAge'] ?? null;
            $maxAge = $filters['maxReadingAge'] ?? null;

            $matched = array_values(array_filter($books, function ($b) use ($min, $max, $age, $minAge, $maxAge) {
                $p = isset($b['finalPrice']) ? (float)$b['finalPrice'] : (float)($b['price'] ?? 0);
                $a = (int)($b['readingAge'] ?? 0);

                if ($min !== null && $p < (float)$min) return false;
                if ($max !== null && $p > (float)$max) return false;

                // range age has priority if provided
                if ($minAge !== null && $a < (int)$minAge) return false;
                if ($maxAge !== null && $a > (int)$maxAge) return false;

                // fallback single-age (<=)
                if ($minAge === null && $maxAge === null && $age !== null && $a > (int)$age) return false;

                return true;
            }));

            // If there are matches, return 1–2 matches directly (no LLM)
            if (count($matched) > 0) {
                $picked = array_slice($matched, 0, 2);

                $parts = [];
                $suggestions = [];

                foreach ($picked as $b) {
                    $p = isset($b['finalPrice']) ? (float)$b['finalPrice'] : (float)($b['price'] ?? 0);
                    $parts[] = $b['title'] . " ($" . number_format($p, 2) . ")";
                    $suggestions[] = ['id' => (int)$b['id']];
                }

                // build "want" text (NOW range-friendly)
                $want = [];

                if ($minAge !== null && $maxAge !== null) {
                    $want[] = "ages " . (int)$minAge . "–" . (int)$maxAge;
                } elseif ($minAge !== null) {
                    $want[] = "age " . (int)$minAge . "+";
                } elseif ($maxAge !== null) {
                    $want[] = "up to age " . (int)$maxAge;
                } elseif ($age !== null) {
                    $want[] = "up to age " . (int)$age;
                }

                if ($max !== null) $want[] = "under $" . number_format((float)$max, 2);
                if ($min !== null) $want[] = "above $" . number_format((float)$min, 2);

                return response()->json([
                    "answer" => "Here are good options (" . implode(", ", $want) . "): " . implode(" and ", $parts) . ".",
                    "suggestions" => $suggestions,
                ]);
            }

            // No match: suggest closest alternatives (still no LLM)
            if (count($books) === 0) {
                return response()->json([
                    "answer" => "Currently, we do not have a suitable book for this topic.",
                    "suggestions" => [],
                ]);
            }

            $picked = array_slice($books, 0, 2);

            $parts = [];
            $suggestions = [];

            foreach ($picked as $b) {
                $p = isset($b['finalPrice']) ? (float)$b['finalPrice'] : (float)($b['price'] ?? 0);
                $parts[] = $b['title'] . " ($" . number_format($p, 2) . ")";
                $suggestions[] = ['id' => (int)$b['id']];
            }

            $want = [];

            if ($minAge !== null && $maxAge !== null) {
                $want[] = "ages " . (int)$minAge . "–" . (int)$maxAge;
            } elseif ($minAge !== null) {
                $want[] = "age " . (int)$minAge . "+";
            } elseif ($maxAge !== null) {
                $want[] = "up to age " . (int)$maxAge;
            } elseif ($age !== null) {
                $want[] = "up to age " . (int)$age;
            }

            if ($max !== null) $want[] = "under $" . number_format((float)$max, 2);
            if ($min !== null) $want[] = "above $" . number_format((float)$min, 2);

            return response()->json([
                "answer" => "We currently don't have books matching (" . implode(", ", $want) . "). The closest options we have are " . implode(" and ", $parts) . ".",
                "suggestions" => $suggestions,
            ]);
        }

        /**
         * =====================================================
         * 2.2) NO BOOKS AT ALL
         * =====================================================
         */
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
     * - Never parse age-like phrases (e.g. "around 14 to 16 years old") as price.
     */
    private function parsePriceUsd(string $lower): array
    {
        $min = null;
        $max = null;

        $hasCurrencyHint = preg_match('/(\$|usd|dollar|dollars|price|cost|budget)/i', $lower) === 1;
        $hasAgeHint = preg_match('/(years?\s*old|years?|yrs?|age)\b/i', $lower) === 1;

        // If the message talks about age and there is NO currency hint, do not parse any price.
        if ($hasAgeHint && !$hasCurrencyHint) {
            return ['min' => null, 'max' => null];
        }

        // between $5 and $20 (allow without $ only if currency hint exists)
        if (preg_match('/between\s*\$?(\d+(?:\.\d+)?)\s*(?:and|to)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
            $hasDollarSign = (strpos($m[0], '$') !== false);
            if ($hasDollarSign || $hasCurrencyHint) {
                return ['min' => (float)$m[1], 'max' => (float)$m[2]];
            }
        }

        // under/below/max/<= : require currency hint OR $ sign
        if (preg_match('/(?:under|below|max(?:imum)?|<=)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
            $hasDollarSign = (strpos($m[0], '$') !== false);
            if ($hasDollarSign || $hasCurrencyHint) {
                $max = (float)$m[1];
            }
        }

        // over/above/upper/min/>= : require currency hint OR $ sign
        if (preg_match('/(?:over|above|upper|min(?:imum)?|>=)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
            $hasDollarSign = (strpos($m[0], '$') !== false);
            if ($hasDollarSign || $hasCurrencyHint) {
                $min = (float)$m[1];
            }
        }

        // budget/price around (require currency hint OR $ sign)
        if ($min === null && $max === null) {
            if (preg_match('/(?:budget|around)\s*\$?(\d+(?:\.\d+)?)/i', $lower, $m)) {
                $hasDollarSign = (strpos($m[0], '$') !== false);
                if ($hasDollarSign || $hasCurrencyHint) {
                    $max = (float)$m[1];
                }
            }
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Parse reading age RANGE intent from user message
     * - "14 to 16 years old", "ages 14-16", "14-16 yrs"
     */
    private function parseReadingAgeRange(string $lower): ?array
    {
        // "ages 14 to 16 years old" / "14-16 years" / "14 to 16 yrs"
        if (preg_match('/\b(?:ages?\s*)?(\d{1,2})\s*(?:to|and|-)\s*(\d{1,2})\s*(?:years?\s*old|years?|yrs?)\b/i', $lower, $m)) {
            $a = (int)$m[1];
            $b = (int)$m[2];
            if ($a > $b) { $tmp = $a; $a = $b; $b = $tmp; }
            return ['min' => $a, 'max' => $b];
        }

        return null;
    }

    /**
     * Parse reading age (single) intent from user message
     */
    private function parseReadingAge(string $lower): ?int
    {
        // "12+" or "age 12"
        if (preg_match('/(?:age\s*)?(\d{1,2})\s*\+/', $lower, $m)) return (int)$m[1];
        if (preg_match('/age\s*(\d{1,2})\b/', $lower, $m)) return (int)$m[1];

        // range "7-10" -> use upper bound (kept for backward compatibility)
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
