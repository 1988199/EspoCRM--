<?php
require __DIR__ . '/../files/custom/Espo/Modules/QuoteManagement/Services/Calculator.php';
use Espo\Modules\QuoteManagement\Services\Calculator as C;
$checks = 0;
function same($actual, $expected, $label) {
    global $checks;
    if ($actual !== $expected) { throw new RuntimeException($label . ': ' . json_encode($actual)); }
    $checks++;
}
function invalid(callable $test, string $label) {
    global $checks;
    try { $test(); } catch (InvalidArgumentException $e) { $checks++; return; }
    throw new RuntimeException($label . '应当拒绝');
}
same(C::line(2, 100, 10), 180.0, '行折扣');
same(C::line(0, 100), 0.0, '零数量');
same(C::line(2, 0), 0.0, '零单价');
same(C::line(2, 100, 100), 0.0, '全额折扣');
same(C::line(0.5, 10), 5.0, '小数数量');
same(C::line('3', '12.50', '20'), 30.0, '数字文本');
same(C::line(1, 0.005), 0.01, '半分舍入');
same(C::line(3, 0.333), 1.0, '行舍入');
same(C::totals([180, 150], 30, 13), ['subtotal'=>330.0,'taxAmount'=>39.0,'total'=>339.0], '多行整单折扣税额');
same(C::totals([], 0, 13), ['subtotal'=>0.0,'taxAmount'=>0.0,'total'=>0.0], '空报价');
same(C::totals([100], 0, 0), ['subtotal'=>100.0,'taxAmount'=>0.0,'total'=>100.0], '零税');
same(C::totals([100], 100, 13), ['subtotal'=>100.0,'taxAmount'=>0.0,'total'=>0.0], '全额整单折扣');
same(C::totals([0.01,0.01], 0, 13), ['subtotal'=>0.02,'taxAmount'=>0.0,'total'=>0.02], '分级舍入');
foreach ([-1, null, '', 'abc', INF, NAN] as $v) {
    invalid(fn()=>C::line($v,1), '非法数量');
    invalid(fn()=>C::line(1,$v), '非法单价');
}
invalid(fn()=>C::line(1,1,-1), '负折扣');
invalid(fn()=>C::line(1,1,101), '过大折扣');
invalid(fn()=>C::totals([1],-1,13), '负整单折扣');
invalid(fn()=>C::totals([1],0,101), '过大税率');
invalid(fn()=>C::line(1e308,1e308), '溢出');
echo json_encode(['suite'=>'calculator','passed'=>$checks,'failed'=>0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
