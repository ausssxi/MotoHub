<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ShopAcceptanceReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 店舗受け入れ情報が投稿されたことを管理者に通知（承認待ち）。
 */
final class ShopAcceptanceReportSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ShopAcceptanceReport $report
    ) {}

    public function envelope(): Envelope
    {
        $shopName = $this->report->shop?->name ?? ('shop#'.$this->report->shop_id);

        return new Envelope(
            subject: '【MotoHub】店舗情報の投稿がありました（承認待ち: '.$shopName.'）',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shop-acceptance-report',
        );
    }
}
