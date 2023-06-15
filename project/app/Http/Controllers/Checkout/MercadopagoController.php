<?php

namespace App\Http\Controllers\Checkout;

use App\Classes\GeniusMailer;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Generalsetting;
use App\Models\Invest;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use MercadoPago;
use Auth;
class MercadopagoController extends Controller
{
    public function store(Request $request){
       
        $gs = Generalsetting::findOrFail(1);
        $item_name = $gs->title." Invest";
        $item_number = Str::random(4).time();
        $item_amount = $request->amount;

        $order = new Invest();

        $plan = Plan::whereId($request->plan_id)->first();
        $order['transaction_no'] = $item_number;
        
        $order['user_id'] = $request->user_id;
        $order['plan_id'] = $plan->id;
        $order['currency_id'] = $request->currency_id;
        $order['method'] = $request->method;

        if($request->currency_id){
            $currencyValue = Currency::where('id',$request->currency_id)->first();
            $order['amount'] = $request->amount/$currencyValue->value;
            $profitAmount = ($request->amount * $plan->profit_percentage)/100;
            $order['profit_amount'] = $profitAmount/$currencyValue->value;
        }

        if($plan->lifetime_return){
            $order['lifetime_return'] = 1;
        }

        if($plan->captial_return){
            $order['capital_back'] = 1;
            $order['profit_repeat'] = 0;
        }


      

        $order['payment_status'] = "pending";
        $order['status'] = 0;

        $order['profit_time'] = Carbon::now()->addHours($plan->schedule_hour);
        $order->save();


        $payment_amount =  $request->amount/$currencyValue->value;;
        $data = PaymentGateway::whereKeyword('mercadopago')->first();
        $paydata = $data->convertAutoData();
        MercadoPago\SDK::setAccessToken($paydata['token']);
        $payment = new MercadoPago\Payment();
        $payment->transaction_amount = $payment_amount;
        $payment->token = $request->token;
        $payment->description = 'Checkout '.$gs->title;
        $payment->installments = 1;
        $payment->payer = array(
        "email" => Auth::check() ? Auth::user()->email : 'example@gmail.com'
        );

      
        $payment->save();
        dd($payment);


        if ($payment->status == 'approved') {
            $order['payment_status'] = "completed";
            $order['status'] = 1;
            $order->save();
        }


        $user = User::whereId($order->user_id)->first();
        if($gs->is_smtp == 1)
        {
        $data = [
            'to' => $user->email,
            'type' => "Invest",
            'cname' => $user->name,
            'oamount' => $order->amount,
            'aname' => "",
            'aemail' => "",
            'wtitle' => "",
        ];

        $mailer = new GeniusMailer();
        $mailer->sendAutoMail($data);            
        }
        else
        {
           $to = $user->email;
           $subject = " You have invested successfully.";
           $msg = "Hello ".$user->name."!\nYour invest are pending. You have to wait admin approval\nThank you.";
           $headers = "From: ".$this->gs->from_name."<".$this->gs->from_email.">";
           mail($to,$subject,$msg,$headers);            
        }
        return redirect()->route('user.invest.history')->with('message','Invest successfully complete.');
    }
}
