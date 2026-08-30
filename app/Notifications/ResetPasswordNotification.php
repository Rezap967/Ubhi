<?php

// app/Notifications/ResetPasswordNotification.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $rawToken,
        protected string $email
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $resetUrl = config('app.frontend_url').'/reset-password'
            .'?email='.urlencode($this->email)
            .'&token='.$this->rawToken;

        return (new MailMessage)
            ->subject('Instruksi Reset Password - UbhiSir')
            ->line('Kami menerima permintaan untuk mereset password akun Anda.')
            ->action('Reset Password', $resetUrl)
            ->line('Link ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika Anda tidak meminta ini, abaikan email ini — password Anda tidak akan berubah.');
    }
}
