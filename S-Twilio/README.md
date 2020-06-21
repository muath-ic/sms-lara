1- Add ```S-Twilio folder to your custom packages```

2- add this helper function

```php
if (! function_exists('sendSMS')) {
    /**
     * Send SMS by Twilio
     *
     * @param string $receiver_number .
     *
     * @return void
     */
    function sendSMS($receiver_number)
    {
        $twilio = new TwoFactorAuth();
        return $twilio->sendMessage($receiver_number);
    }
}
```
