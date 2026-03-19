<?php

namespace App\Ai;

use Illuminate\Support\Facades\Log;

/**
 * ToolMatcher
 *
 * Decides whether the user's message can be handled by an existing Laravel Tool,
 * or must be forwarded to AgentGeneral.
 *
 * Scoring strategy
 * ----------------
 * Laravel Tools are the first-class handlers. AgentGeneral is a fallback of
 * last resort — it is slower, consumes more resources, and may generate a new
 * skill unnecessarily if an existing Tool already covers the request.
 *
 * Scoring uses normalised keyword overlap (Jaccard-style) between the user
 * message and each Tool's description() string:
 *
 *   score = |message_words ∩ tool_words| / |message_words|
 *
 * Dividing by message words (not union) rewards tools that cover the query
 * well, without penalising tools for having a rich description.
 *
 * A tool is selected only when:
 *   1. score >= MATCH_THRESHOLD (0.35 — deliberately permissive on the tool
 *      side; we prefer a false-positive Tool call over an unnecessary Agent call)
 *   2. The winning tool's score is at least MIN_MARGIN above the second-best
 *      (0.15) — prevents selecting a tool that barely beats another on a
 *      generic word like "get" or "make"
 *
 * If no tool clears both gates, the request goes to AgentGeneral.
 */
class ToolMatcher
{
    /** Minimum overlap score for any tool to be considered. */
    private const MATCH_THRESHOLD = 0.35;

    /**
     * Minimum gap between 1st and 2nd place scores.
     * Prevents weak, ambiguous matches from winning.
     */
    private const MIN_MARGIN = 0.15;

    /**
     * Words that appear in almost every query and tool description.
     * Matching on these inflates scores without adding signal.
     */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'shall', 'can', 'need', 'dare', 'ought',
        'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'she', 'it',
        'they', 'them', 'their', 'this', 'that', 'these', 'those',
        'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from',
        'and', 'or', 'but', 'not', 'no', 'so', 'if', 'as', 'up',
        'what', 'how', 'when', 'where', 'who', 'which', 'why',
        'give', 'get', 'make', 'use', 'using', 'show', 'tell', 'let',
        'want', 'like', 'just', 'please', 'help', 'me', 'about',
        'some', 'any', 'all', 'each', 'every', 'both', 'more', 'most',
    ];

    /**
     * @param  array<string, string>  $tools   class => description() string
     * @param  string                 $message  the user's raw message
     * @return string|null            winning class name, or null → send to Agent
     */
    public function match(array $tools, string $message): ?string
    {
        $messageWords = $this->tokenize($message);

        if (empty($messageWords)) {
            Log::debug('[ToolMatcher] Empty message after tokenisation — skipping tool match.');
            return null;
        }

        $scores = [];

        foreach ($tools as $class => $description) {
            $toolWords = $this->tokenize($description);
            $score     = $this->overlapScore($messageWords, $toolWords);
            $scores[$class] = $score;

            Log::debug(sprintf(
                '[ToolMatcher] %-55s → score: %.2f | words matched: %s',
                $class,
                $score,
                implode(', ', array_intersect($messageWords, $toolWords)) ?: '—'
            ));
        }

        arsort($scores);
        $ranked = array_keys($scores);

        if (empty($ranked)) {
            return null;
        }

        $bestClass = $ranked[0];
        $bestScore = $scores[$bestClass];
        $secondScore = isset($ranked[1]) ? $scores[$ranked[1]] : 0.0;

        // Gate 1: must clear the minimum threshold
        if ($bestScore < self::MATCH_THRESHOLD) {
            Log::info(sprintf(
                '[ToolMatcher] No match — best score %.2f below threshold %.2f.',
                $bestScore,
                self::MATCH_THRESHOLD
            ));
            return null;
        }

        // Gate 2: must lead the second-best by at least MIN_MARGIN
        $margin = $bestScore - $secondScore;
        if ($margin < self::MIN_MARGIN) {
            Log::info(sprintf(
                '[ToolMatcher] No match — winner %s (%.2f) leads second by %.2f, below margin %.2f. Ambiguous.',
                class_basename($bestClass),
                $bestScore,
                $margin,
                self::MIN_MARGIN
            ));
            return null;
        }

        Log::info(sprintf(
            '[ToolMatcher] Match: %s (score: %.2f, margin: %.2f)',
            $bestClass,
            $bestScore,
            $margin
        ));

        return $bestClass;
    }

    /**
     * Normalised keyword overlap:
     *   score = |query ∩ tool| / |query|
     *
     * Measures "how much of the user's query does this tool cover?"
     * A tool covering 4 of 5 query words scores 0.80 regardless of how
     * many extra words its description has.
     */
    private function overlapScore(array $queryWords, array $toolWords): float
    {
        if (empty($queryWords)) {
            return 0.0;
        }

        $toolSet      = array_flip($toolWords); // O(1) lookup
        $intersection = 0;

        foreach ($queryWords as $word) {
            if (isset($toolSet[$word])) {
                $intersection++;
            }
        }

        return $intersection / count($queryWords);
    }

    /**
     * Lowercase, strip punctuation, split on whitespace, remove stop words.
     * Returns a de-duplicated array of meaningful words.
     */
    private function tokenize(string $text): array
    {
        $text  = strtolower($text);
        $text  = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        $stopWords = array_flip(self::STOP_WORDS);
        $filtered  = array_filter($words, fn($w) => !isset($stopWords[$w]) && strlen($w) > 1);

        return array_values(array_unique($filtered));
    }
}