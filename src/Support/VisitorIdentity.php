<?php

namespace Siberfx\ChatHybridAi\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Signed visitor ids. The page that renders the chat signs the logged-in
 * user's id with the app key; the API trusts only ids that verify, so a
 * visitor can't claim to be someone else.
 *
 * Format: {hex id}.{expires}.{hmac}
 */
class VisitorIdentity
{
    public static function sign(Model|int|string $user, ?int $ttl = null): string
    {
        $id = (string) ($user instanceof Model ? $user->getKey() : $user);
        $expires = now()->addSeconds($ttl ?? (int) config('chat-hybrid-ai.identity.ttl', 86400))->getTimestamp();
        $payload = bin2hex($id).'.'.$expires;

        return $payload.'.'.static::hmac($payload);
    }

    /**
     * The user id, or null when the token is missing, forged or expired.
     */
    public static function verify(?string $token): ?string
    {
        if (! is_string($token) || substr_count($token, '.') !== 2) {
            return null;
        }

        [$hex, $expires, $signature] = explode('.', $token);

        if (! hash_equals(static::hmac("{$hex}.{$expires}"), $signature) || (int) $expires < now()->getTimestamp()) {
            return null;
        }

        $id = ctype_xdigit($hex) && strlen($hex) % 2 === 0 ? hex2bin($hex) : false;

        return $id === false || $id === '' ? null : $id;
    }

    protected static function hmac(string $payload): string
    {
        return hash_hmac('sha256', 'chat-hybrid-ai|'.$payload, (string) config('app.key'));
    }
}
