<?php

/** Public API result mapping; no GLPI storage or transport side effects. */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginCheckwarrantyResult
{
    public static function date($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = substr($value, 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function normalize(array $response): ?array
    {
        if (!in_array($response['status'] ?? 'completed', ['completed'], true)) {
            return null;
        }
        $warranty = $response['warranty'] ?? [];
        if (!is_array($warranty)) {
            return null;
        }
        $specs = [];
        $details = $warranty['details'] ?? [];
        foreach (['processor' => 'cpu', 'memory' => 'ram', 'hardDrive' => 'storage', 'display' => 'display'] as $source => $target) {
            $value = is_array($details) ? ($details[$source] ?? null) : null;
            if (is_string($value) && !in_array(strtolower(trim($value)), ['', 'unknown', 'n/a', 'none', 'not found', '-'], true)) {
                $specs[$target] = trim($value);
            }
        }
        $warranty['startDate'] = self::date($warranty['startDate'] ?? null);
        $warranty['endDate'] = self::date($warranty['endDate'] ?? null);
        $tier = $warranty['coverageType'] ?? '';
        $warranty['coverageType'] = is_string($tier) ? trim($tier) : '';
        $useful = $specs || $warranty['startDate'] || $warranty['endDate']
            || $warranty['coverageType'] || in_array($warranty['status'] ?? '', ['active', 'expired'], true);
        return $useful ? ['warranty' => $warranty, 'specs' => $specs] : null;
    }

    public static function infocomPayload(array $result, array $existing, bool $syncSpecs = true): array
    {
        $warranty = $result['warranty'];
        $start = $warranty['startDate'];
        $end = $warranty['endDate'];
        $payload = [];
        if ($start && $end && ($warranty['startDateSource'] ?? '') !== 'estimated') {
            $first = new DateTimeImmutable($start);
            $last = new DateTimeImmutable($end);
            $months = ((int) $last->format('Y') - (int) $first->format('Y')) * 12
                + (int) $last->format('m') - (int) $first->format('m');
            $day = min((int) $first->format('d'), (int) $last->format('t'));
            if ($months > 0 && (int) $last->format('d') === $day) {
                $payload['warranty_date'] = $start;
                $payload['warranty_duration'] = $months;
            }
        }
        $parts = [];
        if ($end) {
            $parts[] = 'expiry=' . $end;
        }
        if ($warranty['coverageType']) {
            $parts[] = 'SLA=' . $warranty['coverageType'];
        }
        if ($syncSpecs) {
            foreach ($result['specs'] as $key => $value) {
                $parts[] = strtoupper($key) . '=' . $value;
            }
        }
        if ($parts) {
            $manual = rtrim(preg_replace('/(?:\n)?\[CheckWarranty\][^\n]*/', '', $existing['warranty_info'] ?? ''));
            $note = '[CheckWarranty] ' . str_replace(["\r", "\n"], ' ', implode('; ', $parts));
            $available = 255 - mb_strlen($manual) - ($manual !== '' ? 1 : 0);
            if ($available >= mb_strlen('[CheckWarranty] ' . $parts[0])) {
                $payload['warranty_info'] = ($manual !== '' ? $manual . "\n" : '') . mb_substr($note, 0, $available);
            }
        }
        return $payload;
    }
}
