<?php
declare(strict_types=1);

final class Daily
{
    public static function calendar(?DateTimeImmutable $instant = null): array
    {
        $utc = new DateTimeZone('UTC');
        $now = ($instant ?? new DateTimeImmutable('now', $utc))->setTimezone($utc);
        $local = $now->setTimezone(new DateTimeZone('America/Toronto'));
        return [
            'date' => $local->format('Y-m-d'), 'serverNow' => $now->format(DATE_ATOM),
            'resetAt' => $local->modify('tomorrow midnight')->setTimezone($utc)->format(DATE_ATOM),
        ];
    }

    public static function stream(array $game, array $rules, string $action, array $input, string $seed): ?callable
    {
        $base = ['simple-slot/daily/v1', $game['dailyDate'], $rules['version']];
        switch ($action) {
            case 'spin':
                $address = ['normal', $game['normalUsed'] + 1];
                break;
            case 'respin':
                $address = ['respin', $game['normalUsed'], $input['reel'] ?? null];
                break;
            case 'advance_bonus':
                $ordinal = $rules['bonusSpins'] - $game['bonusRemaining'] + 1;
                $address = ['bonus', $game['normalUsed'], $ordinal];
                break;
            default:
                return null;
        }
        return Engine::stream($seed, json_encode(array_merge($base, $address), JSON_THROW_ON_ERROR));
    }
}
