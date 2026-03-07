<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Models\Listing;
use App\Models\PriceHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceDropMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Listing $listing,
        public PriceHistory $priceHistory,
    ) {}

    public function envelope(): Envelope
    {
        $bikeName = $this->listing->title ?? $this->listing->bikeModel?->name ?? 'バイク';
        $diff = number_format(($this->priceHistory->old_price - $this->priceHistory->new_price) / 10000, 1);

        return new Envelope(
            subject: "【MotoHub】{$bikeName} が {$diff}万円値下げされました！",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.price_drop',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
