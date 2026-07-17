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
 * 新機能告知メール（全ユーザー向け・運営からのお知らせ）。
 * プロフィールアイコン／愛車ガレージのコメント・いいね／マイコンテンツ を案内。
 * 宛名 {name} は user->name（未設定時は「ライダー」）＝既存告知メール([[GarageLaunchAnnouncement]])の流儀。
 * 配信は1人ずつ個別送信（複数アドレスを To/CC にまとめない）＝ mail:new-features コマンド側で担保。
 */
class NewFeaturesAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【MotoHub】新機能を追加しました（プロフィールアイコン・愛車ガレージ）',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new_features_announcement',
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
