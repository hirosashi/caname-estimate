<?php
declare(strict_types=1);
// 明細書の計算が Excel の数式結果と一致するかを確認する（サンプル案件を投入済みであること）
// ※ 元Excelの工事項目4・5と見積書（印刷用）はキャッシュ値が再計算前の古い状態のため、数式で再計算した値を期待値にしている
require __DIR__ . '/bootstrap.php';

use App\Core\Db;
use App\Services\Estimate;

$expect = [ // Excel 明細書の小計（AK列）とサマリー
    'sections' => [725500, 4233150, 45205040, 3055520, 3943590],
    'material_total' => 24197451, 'labor_count_total' => 516.3218, 'labor_total' => 13424368, 'quote_total' => 57162800,
];
$p = Db::row("SELECT * FROM projects WHERE name LIKE '%本社屋根外壁改修工事%' ORDER BY id LIMIT 1");
if ($p === null) { echo "sample project not found\n"; exit(1); }
$est = Estimate::calc($p);
$ok = true;
function check(string $label, float $got, float $exp): void {
    global $ok;
    $pass = abs($got - $exp) < 0.5;
    if (!$pass) $ok = false;
    printf("%-20s %s got=%s exp=%s\n", $label, $pass ? 'OK ' : 'NG ', number_format($got, 2), number_format($exp, 2));
}
foreach ($est['sections'] as $i => $s) { check('section ' . ($i + 1), $s['quote_total'], (float)($expect['sections'][$i] ?? 0)); }
check('material_total', $est['material_total'], $expect['material_total']);
check('labor_count_total', $est['labor_count_total'], $expect['labor_count_total']);
check('labor_total', $est['labor_total'], $expect['labor_total']);
check('quote_total', $est['quote_total'], $expect['quote_total']);
$sum = Estimate::summary($p, $est);
check('direct_cost', $sum['direct_cost'], 37621819);
check('net20', $sum['net']['20'], 47030000);
check('expected_quote', $sum['expected_quote'], 68410000);
check('quote_total(税抜)', $sum['quote_total'], 67452104);
echo $ok ? "ALL OK\n" : "FAILED\n";
exit($ok ? 0 : 1);
