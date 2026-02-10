<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Database\Database;

final class AnchorAnalysisService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Analyze anchor texts for a scan and store summaries.
     *
     * @return array<string, mixed>
     */
    public function analyze(int $scanId, string $rootDomain = ''): array
    {
        $links = $this->db->fetchAll(
            'SELECT sl.anchor_text FROM scan_links sl '
            . 'JOIN scan_results sr ON sr.id = sl.result_id '
            . 'WHERE sr.scan_id = ? AND sl.is_target = 1 AND sl.anchor_text IS NOT NULL AND sl.anchor_text != ?',
            [$scanId, '']
        );

        $anchors = [];
        foreach ($links as $link) {
            $text = trim((string) $link['anchor_text']);
            if ($text === '') {
                continue;
            }
            $lower = mb_strtolower($text, 'UTF-8');
            if (!isset($anchors[$lower])) {
                $anchors[$lower] = ['text' => $text, 'count' => 0];
            }
            $anchors[$lower]['count']++;
        }

        // Clear old summaries
        $this->db->execute('DELETE FROM anchor_summaries WHERE scan_id = ?', [$scanId]);

        $totalAnchors = array_sum(array_column($anchors, 'count'));
        $categories = ['exact_match' => 0, 'partial_match' => 0, 'branded' => 0, 'naked_url' => 0, 'generic' => 0];
        $rootLower = mb_strtolower($rootDomain, 'UTF-8');

        foreach ($anchors as $lower => $data) {
            $category = $this->classifyAnchor($lower, $rootLower);
            $categories[$category] = ($categories[$category] ?? 0) + $data['count'];

            $this->db->execute(
                'INSERT INTO anchor_summaries(scan_id, anchor_text, occurrences, category, created_at) VALUES (?, ?, ?, ?, ?)',
                [$scanId, $data['text'], $data['count'], $category, gmdate('c')]
            );
        }

        $overOptimized = $totalAnchors > 0
            && isset($categories['exact_match'])
            && ($categories['exact_match'] / $totalAnchors) > 0.6;

        return [
            'scan_id' => $scanId,
            'total_anchors' => $totalAnchors,
            'unique_anchors' => count($anchors),
            'categories' => $categories,
            'over_optimized' => $overOptimized,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForScan(int $scanId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM anchor_summaries WHERE scan_id = ? ORDER BY occurrences DESC',
            [$scanId]
        );
    }

    private function classifyAnchor(string $anchor, string $rootDomain): string
    {
        // Naked URL
        if (preg_match('#^https?://#i', $anchor) || preg_match('#^www\.#i', $anchor)) {
            return 'naked_url';
        }

        // Generic
        $generics = ['click here', 'read more', 'learn more', 'here', 'this', 'website', 'link', 'source', 'more info', 'details'];
        if (in_array($anchor, $generics, true)) {
            return 'generic';
        }

        // Branded (contains root domain)
        if ($rootDomain !== '' && str_contains($anchor, $rootDomain)) {
            return 'branded';
        }

        // Exact match (contains only keywords, heuristic: less than 5 words)
        $wordCount = str_word_count($anchor);
        if ($wordCount <= 3) {
            return 'exact_match';
        }

        return 'partial_match';
    }
}
