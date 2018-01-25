<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class VerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    
    /**
     * Verification token.
     *
     * @return $this
     */
    protected $token;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        URL::forceRootUrl(Config::get('app.url'));
        return $this->view('mail')
            ->subject('[Triple] 驗證您的電子郵件地址')
            ->with([
                'introLines' => array('請點擊以下連結即可完成帳號認證： '),
                'actionText' => secure_url('confirm', $this->token),
                'actionUrl' => secure_url('confirm', $this->token),
                'outroLines' => array('基於安全性考量，認證有效時間為送出認證信開始的24 小時內。', '逾時請重新申請認證信。', '如果你沒有申請重置密碼，請無視此通知 '),
            ]);
    }
}
