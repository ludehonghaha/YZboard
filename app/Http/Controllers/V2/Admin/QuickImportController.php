<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use App\Utils\Helper;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

class QuickImportController extends Controller
{
    /**
     * Subscription users for the admin quick-import dialog.
     * Do not send any subscription tokens until an account is selected.
     */
    public function users()
    {
        $service = app(UserService::class);
        $users = User::query()
            ->where('is_admin', false)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'email' => $user->email,
                'active' => $service->isAvailable($user)
                    && ($user->u + $user->d) < $user->transfer_enable,
            ]);

        return $this->success($users);
    }

    /**
     * Admin-only links for one selected user. The actual subscription URL
     * is the stable HTTPS URL; the import schemes only hand it to the app.
     */
    public function links(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|min:1|exists:v2_user,id',
        ]);
        $user = User::findOrFail($data['user_id']);
        if ($user->is_admin) {
            return response()->json(['message' => '请选择普通测试用户'], 422);
        }
        if (!app(UserService::class)->isAvailable($user)
            || $user->u + $user->d >= $user->transfer_enable) {
            return response()->json(['message' => '用户订阅已失效、过期或流量不足'], 422);
        }

        $plainUrl = Helper::getSubscribeUrl($user->token);
        $separator = str_contains($plainUrl, '?') ? '&' : '?';
        $shadowUrl = $plainUrl . $separator . 'flag=shadowrocket';
        $mihomoUrl = $plainUrl . $separator . 'flag=meta';

        $encoded = rtrim(strtr(base64_encode($shadowUrl), '+/', '-_'), '=');
        $siteName = (string) admin_setting('app_name', 'YZboard');
        $shadowLink = 'shadowrocket://add/sub://' . $encoded . '?remark=' . rawurlencode($siteName);
        $clashLink = 'clash://install-config?url=' . rawurlencode($mihomoUrl);

        $qrWriter = new Writer(new ImageRenderer(
            new RendererStyle(240, 1), new SvgImageBackEnd()
        ));
        $mobilePage = route('client.import', ['token' => $user->token]);
        $svgBase64 = base64_encode($qrWriter->writeString($mobilePage));

        return $this->success([
            'subscription_url' => $plainUrl,
            'shadowrocket_url' => $shadowUrl,
            'mihomo_url' => $mihomoUrl,
            'shadowrocket_import' => $shadowLink,
            'clash_import' => $clashLink,
            'mobile_qr' => 'data:image/svg+xml;base64,' . $svgBase64,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
