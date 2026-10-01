<?php
namespace Espo\Modules\QuoteManagement\Services;

/** 两位小数报价：先舍入行金额，再汇总；不做汇率换算。 */
final class Calculator
{
    public static function number(mixed $value, string $label, ?float $max = null): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 ||
            ($max !== null && (float) $value > $max)) {
            throw new \InvalidArgumentException($label . '不在允许的非负数值范围内。');
        }
        return (float) $value;
    }
    public static function line(mixed $quantity, mixed $price, mixed $discount = 0): float
    {
        $value = self::number($quantity, '数量') * self::number($price, '单价') *
            (1 - self::number($discount, '折扣百分比', 100) / 100);
        if (!is_finite($value)) { throw new \InvalidArgumentException('金额溢出。'); }
        return round($value, 2, PHP_ROUND_HALF_UP);
    }
    public static function totals(array $amounts, mixed $discount = 0, mixed $rate = 0): array
    {
        $subtotal = 0.0;
        foreach ($amounts as $amount) { $subtotal += self::number($amount, '行金额'); }
        $subtotal = round($subtotal, 2, PHP_ROUND_HALF_UP);
        $discount = round(self::number($discount, '整单折扣'), 2, PHP_ROUND_HALF_UP);
        // 复制报价时先创建表头、再创建明细，暂时允许折扣超过零小计。
        $base = max(0.0, $subtotal - $discount);
        $tax = round($base * self::number($rate, '税率', 100) / 100, 2, PHP_ROUND_HALF_UP);
        $total = round($base + $tax, 2, PHP_ROUND_HALF_UP);
        if (!is_finite($total)) { throw new \InvalidArgumentException('总金额溢出。'); }
        return ['subtotal' => $subtotal, 'taxAmount' => $tax, 'total' => $total];
    }
}
