<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ShopSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 店舗登録の投稿があったことを管理者に通知（承認待ち）。
 */
final class ShopSubmissionSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ShopSubmission $submission
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【MotoHub】店舗登録の投稿がありました（承認待ち: '.$this->submission->shop_name.'）',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shop-submission',
        );
    }
}
