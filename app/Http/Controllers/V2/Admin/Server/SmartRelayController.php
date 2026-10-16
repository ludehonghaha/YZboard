<?php

namespace App\Http\Controllers\V2\Admin\Server;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\ServerRelayService;
use App\Services\SmartRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SmartRelayController extends Controller
{
    public function fetch()
    {
        $entries = SmartRoutingService::validEntries()->map(function (Server $entry) {
            $children = ServerRelayService::isSupportedEntry($entry)
                ? ServerRelayService::childrenOf($entry)->map(fn (Server $child) => [
                    'id' => (int) $child->id,
                    'name' => $child->name,
                    'type' => $child->type,
                ])->values()->all()
                : [];
            return [
                'id' => (int) $entry->id,
                'name' => $entry->name,
                'protocol' => $entry->type,
                'kernel' => Server::effectiveKernelType($entry->kernel_type),
                'children' => $children,
                'smart_relay' => $entry->smart_relay,
            ];
        })->values()->all();

        return $this->success(['entries' => $entries, 'protocols' => SmartRoutingService::PROTOCOLS]);
    }

    public function save(Request $request)
    {
        $input = $request->validate([
            'entry_id' => 'required|integer|min:1',
            'enabled' => 'required|boolean',
            'default_target' => 'required_if:enabled,true|nullable|string|max:80',
            'outbounds' => 'array|max:12',
            'outbounds.*.id' => 'required|string|regex:/^[a-z0-9]{4,16}$/|distinct',
            'outbounds.*.name' => 'required|string|max:64',
            'outbounds.*.protocol' => 'required|string|in:'.implode(',', SmartRoutingService::PROTOCOLS),
            'outbounds.*.server' => [
                'required', 'string', 'max:253',
                'regex:/^(?:[a-z0-9][a-z0-9.-]{0,251}|[a-f0-9:]+)$/i',
            ],
            'outbounds.*.port' => 'required|integer|min:1|max:65535',
            'outbounds.*.username' => 'nullable|string|max:256',
            'outbounds.*.password' => 'nullable|string|max:512',
            'outbounds.*.uuid' => 'nullable|string|max:64',
            'outbounds.*.method' => 'nullable|string|max:80',
            'outbounds.*.sni' => 'nullable|string|max:253',
            'outbounds.*.tls' => 'nullable|boolean',
            'outbounds.*.transport' => 'nullable|in:TCP,UDP',
            'rules' => 'array|max:32',
            'rules.*.name' => 'required|string|max:64',
            'rules.*.kind' => 'required|in:domain_suffix,ip_cidr',
            'rules.*.values' => 'required|array|min:1|max:64',
            'rules.*.values.*' => 'required|string|max:253',
            'rules.*.target' => 'required|string|max:80',
        ]);
        DB::transaction(function () use ($input) {
            $entry = Server::whereKey($input['entry_id'])->lockForUpdate()->first();
            if (!$entry) {
                throw ValidationException::withMessages(['entry_id' => '入口节点不存在']);
            }
            if (!$input['enabled']) {
                $entry->smart_relay = array_merge(
                    ['mode' => 'advanced'],
                    (array) ($entry->smart_relay ?? []),
                    ['enabled' => false]
                );
            } else {
                $entry->smart_relay = SmartRoutingService::validateAndNormalize($entry, $input);
            }
            $entry->save();
        }, 3);
        return $this->success(true);
    }
}
