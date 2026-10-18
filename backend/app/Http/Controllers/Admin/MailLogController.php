<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MailStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MailLogResource;
use App\Models\MailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * メールの送信記録（院長が送信の失敗に気づけるようにする）
 */
class MailLogController extends Controller
{
    /**
     * GET /api/admin/mail-logs
     * 新しい順に50件ずつ。failed_only=1 なら送信に失敗したものだけ
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'failed_only' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = MailLog::query()
            ->with('reservation')
            ->when($request->boolean('failed_only'), fn ($query) => $query->where('status', MailStatus::Failed))
            ->latest('id')
            ->paginate(50);

        return MailLogResource::collection($logs);
    }
}
