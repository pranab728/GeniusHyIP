<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\Generalsetting;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaystackController extends Controller
{
    public $orderRepositorty;
    public  $allusers = [];
    
    public function __construct(OrderRepository $orderRepositorty)
    {
        $this->orderRepositorty = $orderRepositorty;
    }

    public function store(Request $request){
        if($request->currency_code != "NGN")
        {
            return redirect()->back()->with('unsuccess','Please Select NGN Currency For Paystack.');
        }

        $gs = Generalsetting::findOrFail(1);
        $item_name = $gs->title." Invest";
        $item_number = Str::random(4).time();
        $item_amount = $request->amount;


        $addionalData = ['item_number'=>$item_number];
        $this->orderRepositorty->order($request,'running',$addionalData);

        return redirect()->route('user.invest.history')->with('message','Invest successfully complete.');
    }
}
