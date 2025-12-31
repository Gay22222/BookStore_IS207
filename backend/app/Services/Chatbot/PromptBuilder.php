<?php

namespace App\Services\Chatbot;

class PromptBuilder
{
    public function build(string $message, array $books): array
    {
        /**
         * Keep context SHORT but SUFFICIENT:
         * - include price in USD, readingAge, and purpose tags (derived from description)
         */
        $context = "";
        foreach ($books as $b) {
            $price = isset($b['finalPrice']) ? (float)$b['finalPrice'] : (float)($b['price'] ?? 0);
            $priceText = '$' . number_format($price, 2);
            $age = (int)($b['readingAge'] ?? 0);
            $purpose = !empty($b['purposeTags']) ? implode(',', (array)$b['purposeTags']) : 'unknown';

            $context .= "- ({$b['id']}) {$b['title']} | {$b['category']} | price: {$priceText} | readingAge: {$age}+ | purpose: {$purpose}\n";
        }

        $system = <<<SYS
You are a bookstore recommendation assistant.

STRICT RULES (VERY IMPORTANT):
- You MUST use ONLY the books listed in CONTEXT.
- You MUST NOT invent books, authors, or topics not present in CONTEXT.
- You MUST NOT use any external knowledge.
- If CONTEXT contains only ONE book, you MUST recommend ONLY that book.
- If there is no suitable book, say clearly: "Currently, we do not have a suitable book for this topic."

WHAT TO DO:
- Recommend by genre/category when possible.
- Mention the price clearly in USD (e.g., "$12.99").
- Consider readingAge suitability when possible.
- Infer the reading purpose using the book's "purpose" tags.

HOW TO ANSWER:
- Write in natural, friendly English.
- Recommend ONLY 1–2 books.
- For each book: give ONE short reason + include its USD price.
- The answer MUST be 1–2 sentences total (not a list).

OUTPUT FORMAT (JSON ONLY, no extra text, no markdown, no code fences):
{
  "answer": "natural recommendation text (1–2 sentences, includes USD prices)",
  "suggestions": [
    { "id": 1 }
  ]
}
SYS;

        $user = <<<USR
Customer question:
{$message}

Available books (CONTEXT):
{$context}
USR;

        return [
            "messages" => [
                ["role" => "system", "content" => $system],
                ["role" => "user", "content" => $user],
            ],
            // Low temperature to reduce hallucination
            "temperature" => 0.1,
            "max_tokens" => 260,
        ];
    }
}
