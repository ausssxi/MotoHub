<?php

namespace App\Mail;

use App\Models\Listing;
use App\Models\PriceHistory;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceDropMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Listing $listing,
        public PriceHistory $priceHistory
    ) {}

    public function envelope(): Envelope
    {
        $bikeName = $this->listing->title ?? $this->listing->bikeModel?->name ?? 'バイク';
        return new Envelope(
            subject: "【MotoHub】お気に入りの「{$bikeName}」が値下がりしました！",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.price_drop',
        );
    }
}