<?php

namespace App\Http\Controllers\Checkout;

use App;
use URL;
use Auth;
use Hash;
use Config;
use Session;
use Redirect;
use Validator;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Classes\BlockIO;
use App\Models\Transaction;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Classes\GeniusMailer;
use App\Models\Generalsetting;
use App\Classes\CoinPaymentsAPI;
use App\Models\UserNotification;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Invest;
use Illuminate\Support\Facades\Input;
use App\Models\PaymentGateway;
use App\Models\Plan;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Facades\Session as FacadesSession;
use Illuminate\Support\Str;

class BlockIoController extends Controller
{

    public function __construct() {
        
    }

    public function blockioInvest()
    {
        return view('invest.blockio');
    }


    public function blockiocallback(Request $request)
    {

        $fpbt = fopen('blockIO-Response'.time().'.txt', 'w');
        fwrite($fpbt, json_encode($request->all(),true));
        fclose($fpbt);

        $notifyID = $request['notification_id'];
        $amountRec = $request['data']['amount_received'];
        $bitTran = $request['data']['txid'];


            if (Invest::where('notify_id',$notifyID)->exists()){
               
                

                $order = Invest::where('notify_id',$notifyID)->where('payment_status','pending')->first();

                if ($order->coin_amount <= $amountRec) {
                    
                    $data['txnid'] = $bitTran;
                    $order['payment_status'] = "completed";
                    $order->update($data);


                    $trans = new Transaction();
                    $trans->email = auth()->user()->email;
                    $trans->amount = $order->amount;
                    $trans->type = "Invest";
                    $trans->txnid = $order->transaction_no;
                    $trans->user_id = $order->user_id;
                    $trans->save();

                    $gs =  Generalsetting::findOrFail(1);
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
                        $to = $order->customer_email;
                        $subject = " You have invested successfully.";
                        $msg = "Hello ".$order->customer_name."!\nYou have invested successfully.\nThank you.";
                        $headers = "From: ".$gs->from_name."<".$gs->from_email.">";
                        mail($to,$subject,$msg,$headers);
                    }

                }


            }

        
    }

    function curlGetCall($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec ($ch);
        curl_close ($ch);

        return $data;
    }


    public function deposit(Request $request)
    {
        if($request->amount > 0){

            $methods = $request->method;
             $version = 2;
             $coin = "BTC";
             $my_api_key = '';
           

             if($methods == "block.io.ltc"){
                $blockinfo    = PaymentGateway::whereKeyword('block.io.ltc')->first();
                $blocksettings= $blockinfo->convertAutoData();
                $coin = "Litecoin";
                $my_api_key = $blocksettings['blockio_api_ltc'];

            }elseif($methods == "block.io.btc"){
                
            }elseif ($methods == "block.io.dgc"){
                $coin = "Dogecoin";
                $blockinfo    = PaymentGateway::whereKeyword('block.io.dgc')->first();
                $blocksettings= $blockinfo->convertAutoData();
                $my_api_key = $blocksettings['blockio_api_dgc'];

            }
   
            $acc = Auth::user()->id;
            $item_number = Str::random(4).time();;
            
            $item_amount = $request->amount;
            $currency_code = $request->currency_code;
            
         
            $secret = 'ZzsMLGKe162CfA5EcG6j';

            $my_callback_url = 'https://webhook.site/d1905990-28dd-424f-b9f2-ff000f2dd85c';

            $block_io = new BlockIO($my_api_key, $secret, $version);
         
            $biorate = 1;
        
            $coin_amount = round($item_amount / $biorate, 8);

            $root_url = 'https://block.io/api/v2/';
            $addObject = $block_io->get_new_address(array());

            $address = $addObject->data->address;
          
            $notifyObject = $block_io->create_notification(array('type' => 'address', 'address' =>$address , 'url' => $my_callback_url));

            $notifyID = $notifyObject->data->notification_id;

            $order = new Invest();

            $plan = Plan::whereId($request->plan_id)->first();
            $order['transaction_no'] = $item_number;
            
            $order['user_id'] = $request->user_id;
            $order['plan_id'] = $plan->id;
            $order['currency_id'] = $request->currency_id;
            $order['method'] = $request->method;
            $order['coin_amount'] = $coin_amount;
            $order['notify_id'] = $notifyID;
    
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
    
            $order['profit_time'] = IlluminateCarbon::now()->addHours($plan->schedule_hour);
            $order->save();
            							
            $qrcode_url = "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=bitcoin:".$address."?amount=".$coin_amount."&choe=UTF-8";


            Session::put('blockio_address',['address' => $address,'coin' => $coin,'qrcode_url' => $qrcode_url,'amount' => $coin_amount,'currency_value' => $item_amount,'currency_sign' => $request->currency_sign,'accountnumber' => $acc]);
            return redirect('invest/blockio');

        }
        return redirect()->back()->with('error','Please enter a valid amount.')->withInput();

    }

}
