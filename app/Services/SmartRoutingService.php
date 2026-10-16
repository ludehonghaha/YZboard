<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Validation\ValidationException;

/**
 * Validated, kernel-aware routing profiles for a single inbound.
 *
 * Protocol endpoints are *upstream proxy services*, not arbitrary direct IP
 * destinations. We deliberately do not generate generic raw kernel JSON.
 */
class SmartRoutingService
{
    public const PROTOCOLS = ['socks', 'http', 'shadowsocks', 'vless', 'trojan', 'hysteria2', 'mieru'];
    private const INBOUNDS = ['shadowsocks', 'vmess', 'vless', 'trojan', 'hysteria', 'tuic', 'anytls', 'socks', 'http', 'mieru'];

    public static function validEntries()
    {
        return Server::query()->orderBy('sort')->get()->filter(
            fn (Server $node) => !$node->isRelayChild()
                && Server::effectiveKernelType($node->kernel_type) === Server::KERNEL_SINGBOX
                && in_array($node->type, self::INBOUNDS, true)
        );
    }

    /** All node identity rules are scoped to this entry, even when it has relay children. */
    public static function validateAndNormalize(Server $entry, array $input): array
    {
        if ($entry->isRelayChild()
            || Server::effectiveKernelType($entry->kernel_type) !== Server::KERNEL_SINGBOX) {
            throw ValidationException::withMessages(['entry_id' => '通用链式分流目前仅支持 sing-box 入口节点']);
        }

        $children = ServerRelayService::isSupportedEntry($entry)
            ? ServerRelayService::childrenOf($entry)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        $allowed = ['direct' => true];
        foreach ($children as $childId) {
            $allowed["relay-{$childId}"] = true;
        }

        $outbounds = [];
        foreach (($input['outbounds'] ?? []) as $row) {
            $id = (string) $row['id'];
            $protocol = (string) $row['protocol'];
            $tag = "sr-{$id}";
            if (isset($allowed[$tag]) || !in_array($protocol, self::PROTOCOLS, true)) {
                throw ValidationException::withMessages(['outbounds' => '落地标识或协议无效']);
            }
            $allowed[$tag] = true;
            $settings = self::settings($row);
            $outbounds[] = [
                'id' => $id,
                'name' => trim($row['name']),
                'protocol' => $protocol,
                'server' => trim($row['server']),
                'port' => (int) $row['port'],
                'settings' => $settings,
            ];
        }

        $rules = [];
        foreach (($input['rules'] ?? []) as $row) {
            $tag = $row['target'];
            if (!isset($allowed[$tag])) {
                throw ValidationException::withMessages(['rules' => '分流规则所选落地不存在']);
            }
            $values = array_values(array_unique(array_map(
                fn ($v) => strtolower(trim($v)), $row['values']
            )));
            if (count($values) === 0 || count($values) > 64) {
                throw ValidationException::withMessages(['rules' => '每条规则必须有 1～64 个域名或 IP 网段']);
            }
            foreach ($values as $value) {
                $valid = $row['kind'] === 'domain_suffix'
                    ? self::validDomain($value)
                    : self::validCIDR($value);
                if (!$valid) {
                    throw ValidationException::withMessages(['rules' => '规则包含无效的域名或 IP/CIDR：'.$value]);
                }
            }
            $rules[] = [
                'name' => trim($row['name']),
                'kind' => $row['kind'],
                'values' => $values,
                'target' => $tag,
            ];
        }
        if (!isset($allowed[$input['default_target']])) {
            throw ValidationException::withMessages(['default_target' => '默认落地不存在']);
        }

        // Prevent accidental reuse of existing manually managed outbound tags.
        $existing = collect((array) ($entry->custom_outbounds ?? []))->pluck('tag')->map(
            fn ($tag) => strtolower((string) $tag)
        );
        foreach ($outbounds as $item) {
            if ($existing->contains('sr-'.$item['id'])) {
                throw ValidationException::withMessages(['outbounds' => '落地与节点已有自定义出站标签冲突']);
            }
        }

        return [
            'mode' => 'advanced',
            'enabled' => true,
            'default_target' => $input['default_target'],
            'outbounds' => $outbounds,
            'rules' => $rules,
        ];
    }

    /** Explicitly map a small protocol-specific field set to sing-box native. */
    private static function settings(array $row): array
    {
        $p = $row['protocol'];
        $secret = (string) ($row['password'] ?? '');
        $name = trim((string) ($row['username'] ?? ''));
        $uuid = trim((string) ($row['uuid'] ?? ''));
        $method = (string) ($row['method'] ?? '');
        $sni = trim((string) ($row['sni'] ?? ''));
        $tls = (bool) ($row['tls'] ?? false);

        if ($p === 'mieru' && $name === '') {
            throw ValidationException::withMessages(['outbounds' => 'Mieru 落地需要用户名']);
        }
        if (in_array($p, ['socks', 'http'], true) && ($name === '') !== ($secret === '')) {
            throw ValidationException::withMessages(['outbounds' => 'SOCKS5/HTTP 认证需要同时填写用户名和密码，或都留空']);
        }
        if (in_array($p, ['shadowsocks', 'trojan', 'hysteria2', 'mieru'], true) && $secret === '') {
            throw ValidationException::withMessages(['outbounds' => '此落地协议需要密码']);
        }
        if ($p === 'shadowsocks' && !in_array($method, [
            'aes-128-gcm', 'aes-256-gcm', 'chacha20-ietf-poly1305',
            '2022-blake3-aes-128-gcm', '2022-blake3-aes-256-gcm',
        ], true)) {
            throw ValidationException::withMessages(['outbounds' => '请选择有效的 Shadowsocks 加密算法']);
        }
        if ($p === 'vless' && !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i', $uuid)) {
            throw ValidationException::withMessages(['outbounds' => 'VLESS 落地需要有效的 UUID']);
        }
        if (in_array($p, ['hysteria2', 'trojan'], true)) {
            $tls = true;
        }
        if ($p === 'hysteria2' && $sni === '') {
            throw ValidationException::withMessages(['outbounds' => 'Hysteria2 需要 TLS SNI']);
        }
        $settings = [
            'server' => trim($row['server']),
            'server_port' => (int) $row['port'],
        ];
        if (in_array($p, ['socks', 'http', 'mieru'], true) && $name !== '') {
            $settings['username'] = $name;
        }
        if ($p === 'socks') {
            $settings['version'] = '5';
        }
        if ($secret !== '') {
            $settings['password'] = $secret;
        }
        if ($p === 'vless') {
            $settings['uuid'] = $uuid;
        }
        if ($p === 'shadowsocks') {
            $settings['method'] = $method;
        }
        if ($p === 'mieru') {
            $settings['transport'] = $row['transport'] ?? 'TCP';
        }
        if ($tls) {
            $settings['tls'] = ['enabled' => true];
            if ($sni !== '') {
                $settings['tls']['server_name'] = $sni;
            }
        }
        return $settings;
    }

    public static function outboundConfigs(array $settings): array
    {
        return array_map(fn ($item) => [
            'tag' => 'sr-'.$item['id'],
            'protocol' => $item['protocol'],
            'settings' => $item['settings'],
        ], $settings['outbounds'] ?? []);
    }

    private static function validDomain(string $value): bool
    {
        $value = preg_replace('/^\*\./', '', $value);
        return strlen($value) <= 253
            && (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $value);
    }

    private static function validCIDR(string $value): bool
    {
        [$ip, $bits] = array_pad(explode('/', $value, 2), 2, null);
        $addr = filter_var($ip, FILTER_VALIDATE_IP);
        if (!$addr) return false;
        if ($bits === null) return true;
        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
        return ctype_digit($bits) && (int) $bits <= $max;
    }
}
