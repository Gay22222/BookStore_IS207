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
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()], 422);
        }

        $message = (string) $request->input('message');
        $filters = (array) $request->input('filters', []);

        /**
         * =====================================================
         * AUTO MAP KEYWORDS → CATEGORY (ENGLISH ONLY)
         * =====================================================
         */
        $lower = mb_strtolower($message);

        if (!isset($filters['category'])) {
            if (str_contains($lower, 'finance')) {
                $filters['category'] = 'Finance';
            } elseif (str_contains($lower, 'fiction')) {
                $filters['category'] = 'Fiction';
            } elseif (str_contains($lower, 'fantasy')) {
                $filters['category'] = 'Fantasy';
            } elseif (str_contains($lower, 'romance')) {
                $filters['category'] = 'Romance';
            }
        }

        /**
         * =====================================================
         * 1) RETRIEVAL (NO VECTOR DB)
         * =====================================================
         */
        $topN = (int) env('CHATBOT_TOP_N', 8);

        /** @var BookRetrievalService $retriever */
        $retriever = app(BookRetrievalService::class);
        $books = $retriever->search($message, $filters, $topN);

        if (count($books) === 0) {
            return response()->json([
                "answer" => "Currently, we do not have a suitable book for this topic. Please try a different keyword or category.",
            ]);
        }

        /**
         * =====================================================
         * 2) PROMPT (ENGLISH, CONTEXT-LOCKED)
         * =====================================================
         */
        /** @var PromptBuilder $promptBuilder */
        $promptBuilder = app(PromptBuilder::class);
        $payload = $promptBuilder->build($message, $books);

        $base = rtrim(env('LLAMA_BASE_URL', 'http://llama:8080'), '/');

        /**
         * =====================================================
         * 3) CALL LLM (WITH FALLBACKS)
         * =====================================================
         */
        $answerRaw = null;

        // 3.1) OpenAI-style chat completions
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

        // 3.2) Fallback /v1/completions
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
                        "max_tokens" => 250,
                    ]);

                if ($res->ok()) {
                    $json = $res->json();
                    $answerRaw = $json['choices'][0]['text'] ?? null;
                }
            } catch (\Throwable $e) {}
        }

        // 3.3) Fallback llama.cpp /completion
        if (!$answerRaw) {
            $sys = $payload['messages'][0]['content'] ?? '';
            $usr = $payload['messages'][1]['content'] ?? '';
            $prompt = $sys . "\n\n" . $usr . "\n\nASSISTANT:";

            $res = Http::timeout((int)env('CHATBOT_TIMEOUT', 60))
                ->post($base . '/completion', [
                    "prompt" => $prompt,
                    "temperature" => 0.1,
                    "n_predict" => 250,
                ]);

            if ($res->ok()) {
                $json = $res->json();
                $answerRaw = $json['content'] ?? ($json['response'] ?? null);
            }
        }

        if (!$answerRaw) {
            return response()->json([
                "answer" => "The recommendation system is currently unavailable. Please try again later.",
            ], 500);
        }

        $answerRaw = trim((string)$answerRaw);

        /**
         * =====================================================
         * 4) PARSE JSON (ANSWER ONLY)
         * =====================================================
         */
        $jsonText = $this->extractFirstJsonObject($answerRaw);
        $data = $jsonText ? json_decode($jsonText, true) : null;

        if (is_array($data) && !empty($data['answer'])) {
            return response()->json([
                "answer" => trim((string)$data['answer']),
            ]);
        }

        // Final safe fallback (still English, no hallucination)
        return response()->json([
            "answer" => $answerRaw,
        ]);
    }

    /**
     * Extract the first JSON object from LLM output
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
}
