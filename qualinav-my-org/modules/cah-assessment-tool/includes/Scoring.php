<?php
namespace CAH_Assess;

if (!defined('ABSPATH')) exit;

class Scoring {
  public static function calculate(array $definition, array $answers): array {
    $sections = $definition['sections'] ?? [];
    $sectionScores = [];
    $allValues = [];

    foreach ($sections as $section) {
      $qidList = [];
      $values = [];

      foreach (($section['questions'] ?? []) as $q) {
        $qid = $q['id'] ?? '';
        if (!$qid) continue;
        $qidList[] = $qid;

        $val = isset($answers[$qid]) ? (int)$answers[$qid] : null;
        if ($val !== null && $val >= 1 && $val <= 5) {
          $values[] = $val;
          $allValues[] = $val;
        }
      }

      $avg = count($values) ? (array_sum($values) / count($values)) : 0;
      $sectionScores[$section['id']] = [
        'title' => $section['title'] ?? $section['id'],
        'average' => round($avg, 2),
        'answered' => count($values),
        'total' => count($qidList),
      ];
    }

    $overall = count($allValues) ? (array_sum($allValues) / count($allValues)) : 0;
    $overall = round($overall, 2);

    $status = self::status_from_overall($definition, $overall);

    return [
      'overall_score' => $overall,
      'status' => $status,
      'section_scores' => $sectionScores,
      'meta' => [
        'answered_total' => count($allValues),
      ]
    ];
  }

  private static function status_from_overall(array $definition, float $overall): string {
    $thresholds = $definition['thresholds'] ?? [
      'compliant_min' => 4.0,
      'partial_min' => 3.0,
    ];

    $compliantMin = (float)($thresholds['compliant_min'] ?? 4.0);
    $partialMin   = (float)($thresholds['partial_min'] ?? 3.0);

    if ($overall >= $compliantMin) return 'compliant';
    if ($overall >= $partialMin) return 'partial';
    return 'at_risk';
  }
}
