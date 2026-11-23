<?php

namespace App\Support;

class ProcessSupport
{
    public static function whatsappUrl(string $message): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) config('services.velvet.whatsapp_number'));
        if (! $phone) {
            return null;
        }

        if (! str_starts_with($phone, '57')) {
            $phone = '57'.$phone;
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
