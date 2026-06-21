<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 愛車ガレージ ローンチ告知メール（全ユーザー向け・運営からのお知らせ）。
 * 宛名 {name} は user->name（未設定時は「ライダー」）＝既存通知メールの流儀に合わせる。
 */
class GarageLaunchAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【MotoHub】愛車ガレージができました｜給油レシートを撮るだけで燃費・維持費を記録',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.garage_launch',
            with: [
                // 宛名：本名相当の name を使用。未設定は「ライダー」（公開ハンドルは宛名に使わない）。
                'name' => $this->user->name ?: 'ライダー',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
