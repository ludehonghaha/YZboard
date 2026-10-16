<?php

namespace App\Http\Controllers\V2\Admin\Server;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\ServerRelayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Automatic landing for the *entry identity only*. Explicit logical-node
 * subscriptions retain their original manual route selection.
 *
 * The admin middleware protects both endpoints and triggers NodeSyncService
 * through ServerObserver. No GEN2 hardware / remote node is changed here.
 */
class SmartRelayController extends Controller
{
    public function fetch()
    {
        $entries = Server::orderBy('sort')->get()
            ->filter(fn (Server $node) => ServerRelayService::isSupportedEntry($node))
            ->map(function (Server $entry) {
                $children = ServerRelayService::childrenOf($entry)
                    ->map(fn (Server $child) => [
                        'id' => (int) $child->id,
                        'name' => $child->name,
                        'type' => $child->type,
                    ])->values()->all();
                return [
                    'id' => (int) $entry->id,
                    'name' => $entry->name,
                    'children' => $children,
                    'smart_relay' => $entry->smart_relay,
                ];
            })->values()->all();

        return $this->success(['entries' => $entries]);
    }

    public function save(Request $request)
    {
        $input = $request->validate([
            'entry_id' => 'required|integer|min:1',
            'enabled' => 'required|boolean',
            'ai_node_id' => 'required_if:enabled,true|nullable|integer|min:1',
            'default_node_id' => 'required_if:enabled,true|nullable|integer|min:1',
            'domains' => 'required_if:enabled,true|array|max:64',
            'domains.*' => [
                'string',
                'max:253',
                'regex:/^(?:\\*\\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            ],
        ]);

        $enabled = (bool) $input['enabled'];
        $domains = collect($input['domains'] ?? [])
            ->map(fn ($domain) => strtolower(ltrim(trim($domain), '*.')))
            ->unique()->values()->all();
        if ($enabled && $domains === []) {
            throw ValidationException::withMessages(['domains' => '请至少填写一个 AI 域名']);
        }

        DB::transaction(function () use ($input, $enabled, $domains) {
            $entry = Server::whereKey($input['entry_id'])->lockForUpdate()->first();
            if (!$entry || !ServerRelayService::isSupportedEntry($entry)) {
                throw ValidationException::withMessages(['entry_id' => '入口不存在，或不是可用的 VLESS/HY2 中转入口']);
            }

            $ai = (int) ($input['ai_node_id'] ?? 0);
            $fallback = (int) ($input['default_node_id'] ?? 0);
            if ($enabled) {
                $childIds = ServerRelayService::childrenOf($entry)
                    ->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($ai === $fallback
                    || !in_array($ai, $childIds, true)
                    || !in_array($fallback, $childIds, true)) {
                    throw ValidationException::withMessages([
                        'ai_node_id' => 'AI 和默认落地必须不同，且都属于该入口的已启用落地节点',
                    ]);
                }
            }

            $entry->smart_relay = $enabled ? [
                'enabled' => true,
                'ai_node_id' => $ai,
                'default_node_id' => $fallback,
                'domains' => $domains,
            ] : ['enabled' => false];
            $entry->save();
        }, 3);

        return $this->success(true);
    }
}
