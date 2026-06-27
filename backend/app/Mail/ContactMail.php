<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * お問い合わせ内容を管理者に通知するメールクラス
 */
final class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $data フォームの入力内容
     */
    public function __construct(
        public readonly array $data
    ) {}

    /**
     * メールのエンベロープ（件名など）を定義
     */
    public function envelope(): Envelope
    {
        $categories = [
            'feedback' => 'サービスへのご要望・改善案',
            'report'   => '不具合・誤情報の報告',
            'business' => 'ビジネス・提携に関するご相談',
            'api'      => 'API利用・データ提供について',
            'other'    => 'その他',
        ];

        $categoryName = $categories[$this->data['category']] ?? '不明';

        return new Envelope(
            subject: '【MotoHub】お問い合わせがありました（' . $categoryName . '）',
            replyTo: [$this->data['email']],
        );
    }

    /**
     * メールの本文（ビュー）を定義
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
        );
    }
}