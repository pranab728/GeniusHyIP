<?php
/**
 * Created by PhpStorm.
 * User: ShaOn
 * Date: 11/29/2018
 * Time: 12:49 AM
 */

namespace App\Classes;

use App\Models\EmailTemplate;
use App\Models\Generalsetting;
use App\Models\Pagesetting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class GeniusMailer
{
    public function __construct()
    {
        $this->gs = DB::table('generalsettings')->first();
        Config::set('mail.port', $this->gs->smtp_port);
        Config::set('mail.host', $this->gs->smtp_host);
        Config::set('mail.username', $this->gs->smtp_user);
        Config::set('mail.password', $this->gs->smtp_pass);
        Config::set('mail.encryption', $this->gs->smtp_encryption);
    }
    public function sendAutoMail(array $mailData)
    {
        $setup = Generalsetting::first();

        $temp = EmailTemplate::where('email_type','=',$mailData['type'])->first();
        $pageSetting = Pagesetting::first();

        if($mailData['type'] == 'Invest'){
            $body = preg_replace("/{customer_name}/", $mailData['cname'] ,$temp->email_body);
            $sms_body = preg_replace("/{customer_name}/", $mailData['cname'] ,$temp->sms);
        }else{
            $body = preg_replace("/{customer_name}/", $mailData['cname'] ,$temp->email_body);
            $body = preg_replace("/{order_number}/", $mailData['oamount'] ,$body);
            $body = preg_replace("/{admin_name}/", $mailData['aname'] ,$body);
            $body = preg_replace("/{admin_email}/", $mailData['aemail'] ,$body);
            $body = preg_replace("/{website_title}/", $setup->title ,$body);


            $sms_body = preg_replace("/{customer_name}/", $mailData['cname'] ,$temp->sms);
            $sms_body = preg_replace("/{order_number}/", $mailData['oamount'] ,$body);
            $sms_body = preg_replace("/{admin_name}/", $mailData['aname'] ,$body);
            $sms_body = preg_replace("/{admin_email}/", $mailData['aemail'] ,$body);
            $sms_body = preg_replace("/{website_title}/", $setup->title ,$body);
        }

        $data = [
            'email_body' => $body
        ];

        $objDemo = new \stdClass();
        $objDemo->to = $mailData['to'];
        $objDemo->from = $setup->from_email;
        $objDemo->title = $setup->from_name;
        $objDemo->subject = $temp->email_subject;

        $user = User::where('email',$mailData['to'])->first();
        try{
            Mail::send('admin.email.mailbody',$data, function ($message) use ($objDemo) {
                $message->from($objDemo->from,$objDemo->title);
                $message->to($objDemo->to);
                $message->subject($objDemo->subject);
            });

            if($this->gs->send_sms){
                $this->sendSMS($user->phone,$sms_body,$pageSetting->phone);
            }
        }
        catch (\Exception $e){
            //die("Not Sent!");
        }
    }

    public function sendCustomMail(array $mailData)
    {
        $setup = Generalsetting::first();
        $pageSetting = Pagesetting::first();

        $data = [
            'email_body' => $mailData['body']
        ];

        $objDemo = new \stdClass();
        $objDemo->to = $mailData['to'];
        $objDemo->from = $setup->from_email;
        $objDemo->title = $setup->from_name;
        $objDemo->subject = $mailData['subject'];

        $user = User::where('email',$mailData['to'])->first();
        try{
            Mail::send('admin.email.mailbody',$data, function ($message) use ($objDemo) {
                $message->from($objDemo->from,$objDemo->title);
                $message->to($objDemo->to);
                $message->subject($objDemo->subject);
            });

            if($this->gs->send_sms){
                $this->sendSMS($user->phone,$mailData['body'],$pageSetting->phone);
            }
        }
        catch (\Exception $e){
            // die($e);
        }
        return true;
    }

    public function sendSMS($recipient,$message,$from){
        if($this->gs->nexmo_status){
            $this->nexmo($recipient,$message,$from);
        }else if($this->gs->twilio_status){
            $this->twilio($recipient,$message);
        }
      }

    public function twilio($recipient,$message){
        $sid = $this->gs->twilio_account_sid;
        $token =  $this->gs->twilio_auth_token;
        $from_number =  $this->gs->twilio_default_number;
    
        $client = new Client($sid, $token);
        $client->messages->create(
            '+'.$recipient,
            array(
                'from' => $from_number,
                'body' => $message
            )
        );
      }
      
    public  function nexmo(string $recipient,$message,$from){
        $basic  = new \Vonage\Client\Credentials\Basic($this->gs->nexmo_key, $this->gs->nexmo_secret);
        $client = new \Vonage\Client($basic);
        $client->sms()->send(
            new \Vonage\SMS\Message\SMS($recipient, $from, $message)
        );
      }

}